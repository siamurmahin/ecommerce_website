<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Courier\Actions\SettleCod;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Report\DateRange;
use App\Domain\Report\SalesReport;
use App\Domain\Returns\Actions\ReceiveReturn;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\OrderReturn;
use App\Filament\Pages\CodReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReturnsTest extends TestCase
{
    use RefreshDatabase;

    private Product $attar;

    private Product $cap;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->attar = Product::create(['category_id' => $category->id, 'name' => 'আতর', 'slug' => 'attar', 'type' => ProductType::Simple, 'price' => 50000, 'cost_price' => 20000, 'stock' => 10]);
        $this->cap = Product::create(['category_id' => $category->id, 'name' => 'টুপি', 'slug' => 'cap', 'type' => ProductType::Simple, 'price' => 30000, 'cost_price' => 10000, 'stock' => 10]);
    }

    private function delivered(?string $at = null): Order
    {
        $order = Order::create(['access_token' => Str::random(40), 'status' => OrderStatus::Pending, 'customer_name' => 'রহিম', 'customer_phone' => '01712345678', 'address' => 'মিরপুর', 'subtotal' => 130000, 'total' => 130000]);
        $order->update(['number' => 'LJ'.(10000 + $order->id)]);
        $order->items()->create(['product_id' => $this->attar->id, 'name' => 'আতর', 'unit_price' => 50000, 'quantity' => 2, 'line_total' => 100000]);
        $order->items()->create(['product_id' => $this->cap->id, 'name' => 'টুপি', 'unit_price' => 30000, 'quantity' => 1, 'line_total' => 30000]);
        $order->update(['status' => OrderStatus::Delivered]);
        $order->statusHistories()->create(['from' => OrderStatus::Shipped, 'to' => OrderStatus::Delivered])
            ->forceFill(['created_at' => $at ?? now()])->save();

        return $order->refresh();
    }

    private function ask(Order $order, array $items, string $type = 'return')
    {
        return $this->postJson("/api/v1/orders/{$order->number}/returns", [
            'token' => $order->access_token, 'type' => $type, 'reason' => 'damaged', 'note' => 'ভাঙা এসেছে', 'items' => $items,
        ]);
    }

    public function test_customer_can_ask_within_the_window_only_once(): void
    {
        $order = $this->delivered();
        $item = $order->items->firstWhere('name', 'আতর');

        $this->getJson("/api/v1/orders/{$order->number}?token={$order->access_token}")->assertJsonPath('data.returnable', true);
        $this->ask($order, [['order_item_id' => $item->id, 'quantity' => 3]])->assertUnprocessable();
        $this->ask($order, [['order_item_id' => $item->id, 'quantity' => 1]])->assertCreated();
        $this->ask($order, [['order_item_id' => $item->id, 'quantity' => 1]])->assertUnprocessable();
        $this->getJson("/api/v1/orders/{$order->number}?token={$order->access_token}")
            ->assertJsonPath('data.returnable', false)->assertJsonPath('data.returns.0.status', 'requested');

        $old = $this->delivered(now()->subDays(10)->toDateTimeString());
        $this->ask($old, [['order_item_id' => $old->items->first()->id, 'quantity' => 1]])->assertUnprocessable();
        $this->postJson("/api/v1/orders/{$order->number}/returns", ['token' => 'wrong', 'type' => 'return', 'reason' => 'damaged', 'items' => [['order_item_id' => $item->id, 'quantity' => 1]]])->assertNotFound();
    }

    public function test_partial_return_restocks_and_profit_counts_the_refund(): void
    {
        $order = $this->delivered();
        $item = $order->items->firstWhere('name', 'আতর');
        $return = OrderReturn::create(['order_id' => $order->id, 'type' => 'return', 'reason' => 'damaged', 'status' => ReturnStatus::Approved, 'items' => [['order_item_id' => $item->id, 'quantity' => 1]]]);

        app(ReceiveReturn::class)->handle($return);

        $this->assertSame(11, $this->attar->refresh()->stock);
        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
        $this->assertSame(50000, $return->refresh()->itemsValue());
        $return->update(['status' => ReturnStatus::Completed, 'refund_amount' => 50000]);

        $profit = SalesReport::for(DateRange::from('7d'))->profit();
        $this->assertSame(50000, $profit['refunds']);
        $this->assertSame(20000, $profit['restocked']);
        // 130000 goods − 50000 refund − 50000 purchase + 20000 back on the shelf
        $this->assertSame(50000, $profit['gross']);
        $this->assertSame('ভাঙা / নষ্ট এসেছে', SalesReport::for(DateRange::from('7d'))->returns()[0]['reason']);
    }

    public function test_returning_everything_marks_the_order_returned(): void
    {
        $order = $this->delivered();
        $return = OrderReturn::create(['order_id' => $order->id, 'type' => 'return', 'reason' => 'wrong_item', 'status' => ReturnStatus::Approved,
            'items' => $order->items->map(fn ($i) => ['order_item_id' => $i->id, 'quantity' => $i->quantity])->all()]);

        app(ReceiveReturn::class)->handle($return);

        $this->assertSame(OrderStatus::Returned, $order->refresh()->status);
        $this->assertSame(12, $this->attar->refresh()->stock);
        $this->assertSame(0, $return->refresh()->restocked_cost);
    }

    public function test_cod_settlement_records_payment_and_short_payouts(): void
    {
        $paid = $this->delivered();
        $short = $this->delivered();

        app(SettleCod::class)->handle($paid, null, 6000, 'PAY-1');
        $this->assertSame(PaymentStatus::Paid, $paid->refresh()->payment_status);
        $this->assertSame(0, SettleCod::difference($paid));

        $csv = tempnam(sys_get_temp_dir(), 'cod');
        file_put_contents($csv, "Invoice,Collected Amount,Delivery Charge\n{$short->number},1200,60\nLJ-NOPE,500,60\n");
        $result = CodReconciliation::import($csv, 'PAY-2');
        unlink($csv);

        $this->assertSame(['settled' => 1, 'short' => 1, 'unmatched' => ['LJ-NOPE']], $result);
        $this->assertSame(-10000, SettleCod::difference($short->refresh()));
        $this->assertSame(6000, $short->courier_fee);
        $this->assertSame(PaymentStatus::Partial, $short->payment_status);
    }
}

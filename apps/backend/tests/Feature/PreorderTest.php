<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreorderTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingSeeder::class);
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar', 'type' => ProductType::Simple,
            'price' => 45000, 'stock' => 0, 'is_active' => true, 'preorder' => true, 'preorder_note' => 'নভেম্বরের শেষে ডেলিভারি',
        ]);
    }

    private function quote(int $quantity): array
    {
        return $this->postJson('/api/v1/cart/quote', ['items' => [['product_id' => $this->product->id, 'quantity' => $quantity]]])->assertOk()->json('data.lines.0');
    }

    public function test_out_of_stock_preorder_product_can_be_ordered_without_touching_stock(): void
    {
        $this->getJson('/api/v1/products/oud-attar')->assertJsonPath('data.preorder', true)->assertJsonPath('data.preorder_note', 'নভেম্বরের শেষে ডেলিভারি');
        $this->assertSame([true, null], [$this->quote(2)['preorder'], $this->quote(2)['problem']]);
        $this->assertSame('unavailable', $this->quote(21)['problem']); // over the pre-order cap

        $this->postJson('/api/v1/orders', [
            'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod', 'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ])->assertCreated();

        $order = Order::sole();
        $this->assertTrue($order->items->sole()->preorder);
        $this->assertSame(0, $this->product->fresh()->stock);

        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled); // nothing to put back
        $this->assertSame(0, $this->product->fresh()->stock);
    }

    public function test_in_stock_quantities_are_ordinary_and_preorder_off_means_unavailable(): void
    {
        $this->product->update(['stock' => 3]);
        $this->assertFalse($this->quote(2)['preorder']);
        $this->assertTrue($this->quote(5)['preorder']); // more than on hand

        $this->product->update(['preorder' => false]);
        $this->assertSame('insufficient_stock', $this->quote(5)['problem']);
        $this->getJson('/api/v1/products/oud-attar')->assertJsonPath('data.preorder_note', null);
    }
}

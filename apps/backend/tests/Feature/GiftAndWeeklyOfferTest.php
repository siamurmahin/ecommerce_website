<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\FlashSale;
use App\Domain\Order\Models\Order;
use App\Domain\Report\DateRange;
use App\Domain\Report\SalesReport;
use App\Domain\Shipping\Models\District;
use App\Settings\GiftSettings;
use Carbon\CarbonImmutable;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftAndWeeklyOfferTest extends TestCase
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
            'price' => 45000, 'cost_price' => 20000, 'stock' => 5, 'is_active' => true,
        ]);
    }

    private function order(array $extra = []): Order
    {
        $this->postJson('/api/v1/orders', [
            'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod', 'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            ...$extra,
        ])->assertCreated();

        return Order::latest('id')->first();
    }

    public function test_gift_wrap_is_charged_only_when_switched_on(): void
    {
        $off = $this->order(['gift_wrap' => true, 'gift_note' => 'ঈদ মোবারক!']);
        $this->assertFalse($off->gift_wrap);
        $this->assertSame('ঈদ মোবারক!', $off->gift_note); // the note works on its own

        app(GiftSettings::class)->fill(['wrap_enabled' => true, 'wrap_price' => 5000, 'wrap_cost' => 2000])->save();
        $this->getJson('/api/v1/checkout/options')->assertJsonPath('gift', ['wrap' => true, 'wrap_price' => 5000, 'note' => true]);

        $on = $this->order(['gift_wrap' => true, 'gift_note' => 'জন্মদিনের শুভেচ্ছা']);
        $this->assertTrue($on->gift_wrap);
        $this->assertSame($off->total + 5000, $on->total);
        $this->assertSame(2000, $on->gift_wrap_cost);
        $this->getJson("/api/v1/orders/{$on->number}?token={$on->access_token}")->assertJsonPath('data.gift_wrap_charge', 5000);

        // The wrap's price is goods sold; what it cost the shop is an extra cost.
        $profit = SalesReport::for(DateRange::from('7d'))->profit();
        $this->assertSame(2000, $profit['extra']);
    }

    public function test_weekly_sale_moves_to_next_week_after_it_ends(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-31 10:00', 'Asia/Dhaka')); // Saturday
        $sale = FlashSale::create([
            'name' => 'জুম্মার অফার', 'is_active' => true, 'repeats_weekly' => true,
            'starts_at' => CarbonImmutable::parse('2026-10-30 00:00', 'Asia/Dhaka')->utc(), 'ends_at' => CarbonImmutable::parse('2026-10-30 23:59', 'Asia/Dhaka')->utc(),
        ]);
        $once = FlashSale::create(['name' => 'একবার', 'is_active' => true, 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);

        $this->artisan('flash-sales:refresh')->assertSuccessful();

        $this->assertSame('2026-11-06 00:00', $sale->refresh()->starts_at->tz('Asia/Dhaka')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-06 23:59', $sale->ends_at->tz('Asia/Dhaka')->format('Y-m-d H:i'));
        $this->assertTrue($once->refresh()->ends_at->isPast());
        CarbonImmutable::setTestNow();
    }
}

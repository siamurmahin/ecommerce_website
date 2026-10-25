<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Enums\CouponType;
use App\Domain\Marketing\Enums\DiscountType;
use App\Domain\Marketing\Models\Bundle;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\FlashSale;
use App\Domain\Marketing\Models\OrderBump;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingTest extends TestCase
{
    use RefreshDatabase;

    private Product $attar;

    private Product $panjabi;

    private Product $tasbih;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingSeeder::class);
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $make = fn (string $slug, int $price) => Product::create([
            'category_id' => $category->id, 'name' => $slug, 'slug' => $slug,
            'type' => ProductType::Simple, 'price' => $price, 'stock' => 20, 'is_active' => true,
        ]);
        $this->attar = $make('attar', 50000);       // ৳500
        $this->panjabi = $make('panjabi', 200000);  // ৳2000
        $this->tasbih = $make('tasbih', 30000);     // ৳300
    }

    private function quote(array $items, array $extra = []): array
    {
        return $this->postJson('/api/v1/cart/quote', ['items' => $items, ...$extra])->assertOk()->json('data');
    }

    private function item(Product $p, int $qty = 1, ?int $bump = null): array
    {
        return array_filter(['product_id' => $p->id, 'quantity' => $qty, 'bump_id' => $bump]);
    }

    private function order(array $items, array $extra = [])
    {
        return $this->postJson('/api/v1/orders', [
            'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod', 'items' => $items, ...$extra,
        ]);
    }

    public function test_running_flash_sale_lowers_prices_everywhere(): void
    {
        $sale = FlashSale::create(['name' => 'ঈদ সেল', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $sale->items()->create(['product_id' => $this->attar->id, 'discount_type' => DiscountType::Percent, 'discount_value' => 20]);

        $this->getJson('/api/v1/products/attar')
            ->assertJsonPath('data.price', 40000)
            ->assertJsonPath('data.compare_price', 50000)
            ->assertJsonPath('data.flash_sale.name', 'ঈদ সেল');
        $this->getJson('/api/v1/products?flash_sale=1')->assertJsonCount(1, 'data');
        $this->assertSame(40000, $this->quote([$this->item($this->attar)])['lines'][0]['unit_price']);

        $sale->update(['ends_at' => now()->subMinute()]);
        $this->assertSame(50000, $this->quote([$this->item($this->attar)])['lines'][0]['unit_price']);
    }

    public function test_bundle_discount_applies_per_complete_set(): void
    {
        $bundle = Bundle::create(['name' => 'জুমার সেট', 'discount_type' => DiscountType::Percent, 'discount_value' => 10]);
        $bundle->items()->createMany([['product_id' => $this->panjabi->id], ['product_id' => $this->attar->id]]);

        $quote = $this->quote([$this->item($this->panjabi, 2), $this->item($this->attar, 1)]);
        $this->assertSame([['type' => 'bundle', 'label' => 'জুমার সেট', 'amount' => 25000]], $quote['discounts']); // one set: 10% of ৳2500
        $this->assertSame(450000 - 25000, $quote['total']);

        $this->assertSame([], $this->quote([$this->item($this->panjabi)])['discounts']);
        $this->getJson('/api/v1/products/attar')->assertJsonPath('data.bundles.0.saving', 25000);
    }

    public function test_coupon_rules_and_redemption(): void
    {
        Coupon::create(['code' => 'eid20', 'type' => CouponType::Percent, 'value' => 20, 'max_discount' => 30000, 'min_subtotal' => 100000, 'per_phone_limit' => 1, 'usage_limit' => 5]);

        $this->assertSame('min_subtotal', $this->quote([$this->item($this->attar)], ['coupon_code' => 'EID20'])['coupon']['error']);
        $this->assertSame('invalid', $this->quote([$this->item($this->attar)], ['coupon_code' => 'NOPE'])['coupon']['error']);

        $quote = $this->quote([$this->item($this->panjabi)], ['coupon_code' => ' Eid20 ']);
        $this->assertTrue($quote['coupon']['applied']);
        $this->assertSame(30000, $quote['discount_total']); // 20% of ৳2000 = ৳400, capped at ৳300

        $this->order([$this->item($this->panjabi)], ['coupon_code' => 'EID20'])->assertCreated()->assertJsonPath('data.total', 200000 - 30000 + 6000);
        $order = Order::sole();
        $this->assertSame(['EID20', 30000], [$order->coupon_code, $order->discount]);
        $this->assertSame(1, Coupon::sole()->used_count);

        // Same phone again: per-phone limit.
        $this->order([$this->item($this->panjabi)], ['coupon_code' => 'EID20'])->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    }

    public function test_free_shipping_and_item_limited_coupons(): void
    {
        Coupon::create(['code' => 'FREESHIP', 'type' => CouponType::FreeShipping]);
        Coupon::create(['code' => 'ATTAR50', 'type' => CouponType::Amount, 'value' => 5000, 'product_ids' => [$this->attar->id]]);
        $dhaka = District::where('name_en', 'Dhaka')->value('id');

        $this->assertSame(0, $this->quote([$this->item($this->tasbih)], ['coupon_code' => 'FREESHIP', 'district_id' => $dhaka])['delivery_charge']);
        $this->assertSame('not_applicable', $this->quote([$this->item($this->tasbih)], ['coupon_code' => 'ATTAR50'])['coupon']['error']);
        $this->assertSame(5000, $this->quote([$this->item($this->tasbih), $this->item($this->attar)], ['coupon_code' => 'ATTAR50'])['discount_total']);
    }

    public function test_order_bump_is_offered_and_priced_only_when_eligible(): void
    {
        $bump = OrderBump::create(['product_id' => $this->tasbih->id, 'title' => 'সাথে তাসবিহ নিন', 'price' => 15000, 'trigger_product_ids' => [$this->panjabi->id]]);

        $this->assertSame([], $this->quote([$this->item($this->attar)])['bumps']);
        $offer = $this->quote([$this->item($this->panjabi)])['bumps'][0];
        $this->assertSame([$bump->id, 15000, 30000], [$offer['id'], $offer['price'], $offer['regular_price']]);

        $taken = $this->quote([$this->item($this->panjabi), $this->item($this->tasbih, 3, $bump->id)]);
        $this->assertSame([1, 15000, $bump->id], [$taken['lines'][1]['quantity'], $taken['lines'][1]['unit_price'], $taken['lines'][1]['bump_id']]);
        $this->assertSame([], $taken['bumps']);

        // Without the trigger product the bump line is dropped, not charged at full price.
        $this->assertCount(1, $this->quote([$this->item($this->attar), $this->item($this->tasbih, 1, $bump->id)])['lines']);

        $this->order([$this->item($this->panjabi), $this->item($this->tasbih, 1, $bump->id)])->assertCreated();
        $this->assertSame($bump->id, Order::sole()->items()->where('product_id', $this->tasbih->id)->value('order_bump_id'));
    }

    public function test_upsells_and_cross_sells(): void
    {
        $this->attar->upsells()->attach($this->panjabi->id);
        $this->panjabi->crossSells()->attach([$this->attar->id, $this->tasbih->id]);

        $this->getJson('/api/v1/products/attar')->assertJsonPath('data.upsells.0.slug', 'panjabi');
        $this->assertSame(0, $this->panjabi->upsells()->count()); // relation types don't leak into each other

        $slugs = collect($this->getJson("/api/v1/products/cross-sells?ids={$this->panjabi->id},{$this->tasbih->id}")->assertOk()->json('data'))->pluck('slug')->all();
        $this->assertSame(['attar'], $slugs);
    }

    public function test_marketing_admin_screens(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('marketer'));

        foreach (['/admin/coupons', '/admin/flash-sales', '/admin/bundles', '/admin/order-bumps'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));
        $this->get('/admin/coupons')->assertForbidden();
    }
}

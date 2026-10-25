<?php

namespace Tests\Feature\Api;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use App\Settings\ShippingSettings;
use Database\Seeders\ShippingSeeder;
use Database\Seeders\ThanaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $attar;

    private Product $panjabi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingSeeder::class);

        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->attar = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'name_en' => 'Oud Attar', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 5, 'is_active' => true,
        ]);
        $this->panjabi = Product::create([
            'category_id' => $category->id, 'name' => 'পাঞ্জাবি', 'slug' => 'panjabi',
            'type' => ProductType::Variable, 'price' => 150000, 'stock' => 0, 'is_active' => true,
        ]);
        $this->panjabi->variants()->create(['sku' => 'P-M', 'price' => 160000, 'stock' => 2, 'is_active' => true]);
    }

    private function district(string $nameEn): District
    {
        return District::where('name_en', $nameEn)->firstOrFail();
    }

    private function orderPayload(array $overrides = []): array
    {
        return [
            'name' => 'রহিম উদ্দিন',
            'phone' => '+880 1712-345678',
            'district_id' => $this->district('Dhaka')->id,
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০',
            'payment_method' => 'cod',
            'items' => [['product_id' => $this->attar->id, 'quantity' => 2]],
            'event_id' => 'evt-123',
            ...$overrides,
        ];
    }

    public function test_options_list_districts_and_zones(): void
    {
        $this->getJson('/api/v1/checkout/options')
            ->assertOk()
            ->assertJsonCount(3, 'zones')
            ->assertJsonCount(8, 'divisions')
            ->assertJsonPath('payment_methods.0.code', 'cod');
    }

    public function test_quote_uses_database_prices_and_district_zone(): void
    {
        $variant = $this->panjabi->variants->first();

        $this->postJson('/api/v1/cart/quote', [
            'items' => [
                ['product_id' => $this->attar->id, 'quantity' => 1],
                ['product_id' => $this->panjabi->id, 'variant_id' => $variant->id, 'quantity' => 1],
            ],
            'district_id' => $this->district('Sylhet')->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 205000)
            ->assertJsonPath('data.delivery_charge', 12000) // unassigned district falls back to "outside Dhaka"
            ->assertJsonPath('data.total', 217000)
            ->assertJsonPath('data.has_problems', false);
    }

    public function test_quote_flags_stock_problems(): void
    {
        $this->postJson('/api/v1/cart/quote', [
            'items' => [
                ['product_id' => $this->attar->id, 'quantity' => 9],
                ['product_id' => $this->panjabi->id, 'quantity' => 1], // variable product without a variant
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.problem', 'insufficient_stock')
            ->assertJsonPath('data.lines.1.problem', 'unavailable')
            ->assertJsonPath('data.delivery_charge', null);
    }

    public function test_free_delivery_threshold_applies(): void
    {
        $settings = app(ShippingSettings::class);
        $settings->free_delivery_threshold = 80000;
        $settings->save();

        $this->postJson('/api/v1/cart/quote', [
            'items' => [['product_id' => $this->attar->id, 'quantity' => 2]],
            'district_id' => $this->district('Dhaka')->id,
        ])
            ->assertJsonPath('data.free_delivery', true)
            ->assertJsonPath('data.delivery_charge', 0);
    }

    public function test_guest_can_place_cod_order(): void
    {
        $response = $this->postJson('/api/v1/orders', $this->orderPayload())
            ->assertCreated()
            ->assertJsonPath('data.customer_phone', '01712345678')
            ->assertJsonPath('data.subtotal', 90000)
            ->assertJsonPath('data.delivery_charge', 6000)
            ->assertJsonPath('data.total', 96000)
            ->assertJsonPath('data.status', 'pending');

        $number = $response->json('data.number');
        $this->assertSame(3, $this->attar->fresh()->stock);
        $this->assertSame(2, $this->attar->fresh()->sold_count);

        $this->getJson("/api/v1/orders/{$number}?token=".$response->json('access_token'))
            ->assertOk()
            ->assertJsonPath('data.number', $number);
        $this->getJson("/api/v1/orders/{$number}?token=wrong")->assertNotFound();
    }

    public function test_repeated_submit_does_not_create_a_second_order(): void
    {
        $this->postJson('/api/v1/orders', $this->orderPayload())->assertCreated();
        $this->postJson('/api/v1/orders', $this->orderPayload())->assertCreated();

        $this->assertSame(1, Order::count());
        $this->assertSame(3, $this->attar->fresh()->stock);
    }

    public function test_order_is_rejected_for_bad_phone_or_missing_stock(): void
    {
        $this->postJson('/api/v1/orders', $this->orderPayload(['phone' => '01212345678', 'event_id' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->postJson('/api/v1/orders', $this->orderPayload([
            'event_id' => null,
            'items' => [['product_id' => $this->attar->id, 'quantity' => 6]],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(0, Order::count());
    }

    public function test_cancelling_returns_stock_and_reopening_takes_it_again(): void
    {
        $number = $this->postJson('/api/v1/orders', $this->orderPayload())->json('data.number');
        $order = Order::where('number', $number)->firstOrFail();
        $action = app(ChangeOrderStatus::class);

        $action->handle($order, OrderStatus::Cancelled, 'কাস্টমার বাতিল করেছেন');
        $this->assertSame(5, $this->attar->fresh()->stock);

        $action->handle($order->fresh(), OrderStatus::Confirmed);
        $this->assertSame(3, $this->attar->fresh()->stock);
        $this->assertSame(3, $order->statusHistories()->count());
    }

    public function test_guest_can_track_an_order_with_number_and_phone(): void
    {
        $number = $this->postJson('/api/v1/orders', $this->orderPayload())->json('data.number');
        $digits = substr($number, 2);
        $bangla = strtr($digits, array_combine(range(0, 9), ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯']));

        $token = $this->postJson('/api/v1/orders/track', ['number' => 'lj'.$bangla, 'phone' => '+880 1712 345678'])
            ->assertOk()
            ->assertJsonPath('number', $number)
            ->json('access_token');

        $this->getJson("/api/v1/orders/{$number}?token={$token}")
            ->assertOk()
            ->assertJsonPath('data.history.0.status', 'pending');

        $this->postJson('/api/v1/orders/track', ['number' => $number, 'phone' => '01812345678'])->assertNotFound();
    }

    public function test_placing_an_order_refreshes_that_products_stock_on_the_storefront(): void
    {
        config(['shop.storefront_url' => 'http://store.test', 'shop.revalidate_secret' => 'secret']);
        Http::fake();
        $this->withoutDefer();

        $this->postJson('/api/v1/orders', $this->orderPayload())->assertCreated();

        Http::assertSent(fn ($request) => in_array('product:oud-attar', $request['tags'] ?? [], true)
            && in_array('home', $request['tags'], true));
    }

    public function test_order_keeps_a_thana_from_its_own_district_only(): void
    {
        $this->seed(ThanaSeeder::class);
        $mirpur = $this->district('Dhaka')->thanas()->where('name_en', 'Mirpur')->firstOrFail();
        $sylhetThana = $this->district('Sylhet')->thanas()->firstOrFail();

        $thanas = collect($this->getJson('/api/v1/checkout/options')->json('divisions'))
            ->flatMap(fn ($d) => $d['districts'])->firstWhere('id', $mirpur->district_id)['thanas'];
        $this->assertContains($mirpur->id, array_column($thanas, 'id'));

        $this->postJson('/api/v1/orders', $this->orderPayload(['thana_id' => $sylhetThana->id, 'event_id' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('thana_id');

        $this->postJson('/api/v1/orders', $this->orderPayload(['thana_id' => $mirpur->id]))
            ->assertCreated()
            ->assertJsonPath('data.thana', 'Mirpur'); // API answers in the request locale (en in tests)
        $this->assertSame('মিরপুর', Order::first()->thana_name);
    }
}

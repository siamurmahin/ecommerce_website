<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Fraud\FraudService;
use App\Domain\Fraud\Models\FraudCheck;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\IncompleteStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\IncompleteOrder;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Models\User;
use App\Settings\CourierSettings;
use App\Settings\ShippingSettings;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FraudAndIncompleteTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingSeeder::class);
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 20, 'is_active' => true,
        ]);
    }

    private function placeOrder(string $phone = '01712345678'): Order
    {
        $number = $this->postJson('/api/v1/orders', [
            'name' => 'রহিম', 'phone' => $phone, 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.number');

        return Order::where('number', $number)->firstOrFail();
    }

    public function test_internal_history_is_checked_for_each_new_order(): void
    {
        $change = app(ChangeOrderStatus::class);
        $change->handle($this->placeOrder(), OrderStatus::Delivered);
        $change->handle($this->placeOrder(), OrderStatus::Delivered);
        $change->handle($this->placeOrder(), OrderStatus::Delivered);
        $change->handle($this->placeOrder(), OrderStatus::Returned);

        $check = app(FraudService::class)->check('01712345678', fresh: true);

        $this->assertSame([4, 3, 1, 75], [$check->total, $check->delivered, $check->cancelled, $check->success_ratio]);
        $this->assertSame('medium', $check->risk()); // 75% is between 70 and 85
        $this->assertNotNull(FraudCheck::firstWhere('phone', '01712345678')); // created by the auto check on order
    }

    public function test_bdcourier_lookup_is_parsed_and_cached(): void
    {
        app(CourierSettings::class)->fill(['fraud_driver' => 'bdcourier', 'fraud_api_key' => 'bd-key'])->save();
        Http::fake(['api.bdcourier.com/*' => Http::response(['status' => 'success', 'courierData' => [
            'pathao' => ['name' => 'Pathao', 'total_parcel' => 6, 'success_parcel' => 2, 'cancelled_parcel' => 4, 'success_ratio' => 33.3],
            'steadfast' => ['name' => 'Steadfast', 'total_parcel' => 4, 'success_parcel' => 1, 'cancelled_parcel' => 3, 'success_ratio' => 25],
            'redx' => ['name' => 'RedX', 'total_parcel' => 0, 'success_parcel' => 0, 'cancelled_parcel' => 0, 'success_ratio' => 0],
            'summary' => ['total_parcel' => 10, 'success_parcel' => 3, 'cancelled_parcel' => 7, 'success_ratio' => 30],
        ]])]);

        $fraud = app(FraudService::class);
        $check = $fraud->check('01812345678');
        $fraud->check('01812345678');

        Http::assertSentCount(1);
        $this->assertSame(30, $check->success_ratio);
        $this->assertSame('risky', $check->risk());
        $this->assertSame(['pathao', 'steadfast'], array_keys($check->couriers));
    }

    public function test_checkout_draft_is_saved_and_closed_when_the_phone_orders(): void
    {
        $draftId = (string) Str::uuid();
        $payload = [
            'draft_id' => $draftId, 'phone' => '+880 1912-345678', 'name' => 'করিম',
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ];

        $this->postJson('/api/v1/checkout/draft', ['phone' => '0191'] + $payload)->assertUnprocessable();
        $this->postJson('/api/v1/checkout/draft', $payload)->assertNoContent();
        $this->postJson('/api/v1/checkout/draft', $payload + ['address' => 'চকবাজার'])->assertNoContent();

        $draft = IncompleteOrder::sole();
        $this->assertSame(['01912345678', 'চকবাজার', 90000], [$draft->customer_phone, $draft->address, $draft->subtotal]);

        $order = $this->placeOrder('01912345678');

        $this->assertSame(IncompleteStatus::Converted, $draft->fresh()->status);
        $this->assertSame($order->id, $draft->fresh()->order_id);
    }

    public function test_capture_can_be_turned_off(): void
    {
        $settings = app(ShippingSettings::class);
        $settings->capture_incomplete_orders = false;
        $settings->save();

        $this->getJson('/api/v1/checkout/options')->assertJsonPath('capture_incomplete', false);
        $this->postJson('/api/v1/checkout/draft', [
            'draft_id' => (string) Str::uuid(), 'phone' => '01912345678',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ])->assertNoContent();

        $this->assertSame(0, IncompleteOrder::count());
    }

    public function test_staff_can_turn_a_draft_into_an_order(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));
        $draft = IncompleteOrder::create([
            'draft_id' => (string) Str::uuid(), 'customer_phone' => '01912345678', 'customer_name' => 'করিম',
            'district_id' => District::where('name_en', 'Dhaka')->value('id'), 'address' => 'বাড়ি ৫, মিরপুর',
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]], 'last_activity_at' => now(),
        ]);

        $this->get('/admin/incomplete-orders')->assertOk()->assertSee('01912345678');

        Livewire::withQueryParams(['draft' => $draft->id])->test(CreateOrder::class)
            ->assertSchemaStateSet(['phone' => '01912345678', 'name' => 'করিম'])
            ->set('data.notify', false)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(3, Order::sole()->items()->sum('quantity'));
        $this->assertSame(IncompleteStatus::Converted, $draft->fresh()->status);
    }
}

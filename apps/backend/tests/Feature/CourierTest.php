<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Courier\Actions\BookShipment;
use App\Domain\Courier\CourierException;
use App\Domain\Courier\Data\Parcel;
use App\Domain\Courier\Enums\ShipmentStatus;
use App\Domain\Courier\Models\Shipment;
use App\Domain\Order\Actions\PlaceOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use App\Filament\Pages\ManageCourierSettings;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\User;
use App\Settings\CourierSettings;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Database\Seeders\ThanaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CourierTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ShippingSeeder::class, ThanaSeeder::class]);

        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 5, 'is_active' => true,
        ]);

        $dhaka = District::where('name_en', 'Dhaka')->firstOrFail();
        $this->order = app(PlaceOrder::class)->handle([
            'name' => 'রহিম', 'phone' => '01712345678', 'address' => 'বাড়ি ১২, রোড ৪',
            'district_id' => $dhaka->id, 'thana_id' => $dhaka->thanas()->where('name_en', 'Mirpur')->value('id'),
            'payment_method' => 'cod', 'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ]);

        app(CourierSettings::class)->fill([
            'webhook_token' => 'test-webhook-token-123456',
            'steadfast_api_key' => 'sf-key', 'steadfast_secret_key' => 'sf-secret',
            'pathao_client_id' => '7', 'pathao_client_secret' => 'pt-secret', 'pathao_username' => 'shop@example.test',
            'pathao_password' => 'pt-pass', 'pathao_store_id' => 55,
            'redx_token' => 'rx-token',
        ])->save();
    }

    private function book(string $courier, array $parcel = []): Shipment
    {
        return app(BookShipment::class)->handle($this->order, $courier, new Parcel(...['codAmount' => $this->order->total, ...$parcel]));
    }

    public function test_steadfast_booking_marks_the_order_shipped(): void
    {
        Http::fake(['portal.packzy.com/api/v1/create_order' => Http::response([
            'status' => 200, 'consignment' => ['consignment_id' => 1424107, 'tracking_code' => '15BAEB8A', 'status' => 'in_review'],
        ])]);

        $shipment = $this->book('steadfast', ['note' => 'বিকেলে']);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Api-Key', 'sf-key')
            && $r['invoice'] === $this->order->number
            && $r['cod_amount'] === 960 // ৳900 + ৳60 Dhaka delivery
            && str_contains($r['recipient_address'], 'মিরপুর'));
        $this->assertSame('1424107', $shipment->consignment_id);
        $this->assertSame(ShipmentStatus::Booked, $shipment->status);
        $this->assertSame(OrderStatus::Shipped, $this->order->fresh()->status);
        $this->assertSame('https://steadfast.com.bd/t/15BAEB8A', $shipment->trackingUrl());
    }

    public function test_an_order_cannot_be_booked_twice_and_errors_are_readable(): void
    {
        Http::fake(['portal.packzy.com/*' => Http::sequence()
            ->push(['status' => 200, 'consignment' => ['consignment_id' => 1, 'tracking_code' => 'A', 'status' => 'in_review']])
            ->push(['status' => 400, 'errors' => ['recipient_phone' => ['The recipient phone must be 11 digits.']]], 422)]);

        $this->book('steadfast');

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('আগেই Steadfast-এ বুক করা আছে');
        $this->book('steadfast');
    }

    public function test_courier_rejection_message_reaches_staff(): void
    {
        Http::fake(['portal.packzy.com/*' => Http::response(['status' => 400, 'errors' => ['recipient_phone' => ['The recipient phone must be 11 digits.']]], 422)]);

        try {
            $this->book('steadfast');
            $this->fail('Expected CourierException');
        } catch (CourierException $e) {
            $this->assertStringContainsString('recipient phone must be 11 digits', $e->getMessage());
        }
        $this->assertSame(0, Shipment::count());
        $this->assertSame(OrderStatus::Pending, $this->order->fresh()->status);
    }

    public function test_steadfast_webhook_delivers_the_order_and_rejects_bad_tokens(): void
    {
        Http::fake(['portal.packzy.com/*' => Http::response(['status' => 200, 'consignment' => ['consignment_id' => 99, 'tracking_code' => 'X', 'status' => 'in_review']])]);
        $this->book('steadfast');
        $payload = ['notification_type' => 'delivery_status', 'consignment_id' => 99, 'invoice' => $this->order->number, 'status' => 'delivered'];

        $this->postJson('/api/v1/webhooks/courier/steadfast', $payload, ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
        $this->assertSame(OrderStatus::Shipped, $this->order->fresh()->status);

        $this->postJson('/api/v1/webhooks/courier/steadfast', $payload, ['Authorization' => 'Bearer test-webhook-token-123456'])->assertOk();
        $this->assertSame(OrderStatus::Delivered, $this->order->fresh()->status);
        $this->assertSame(ShipmentStatus::Delivered, Shipment::first()->status);
    }

    public function test_returned_parcel_returns_the_stock(): void
    {
        Http::fake(['portal.packzy.com/*' => Http::response(['status' => 200, 'consignment' => ['consignment_id' => 5, 'tracking_code' => 'X', 'status' => 'in_review']])]);
        $this->book('steadfast');
        $this->assertSame(3, $this->product->fresh()->stock);

        $this->postJson('/api/v1/webhooks/courier/steadfast', [
            'notification_type' => 'delivery_status', 'consignment_id' => 5, 'status' => 'cancelled',
        ], ['Authorization' => 'Bearer test-webhook-token-123456'])->assertOk();

        $this->assertSame(OrderStatus::Returned, $this->order->fresh()->status);
        $this->assertSame(5, $this->product->fresh()->stock);
    }

    public function test_pathao_gets_a_token_once_and_books(): void
    {
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response(['access_token' => 'tok', 'expires_in' => 432000]),
            '*/aladdin/api/v1/orders' => Http::response(['code' => 200, 'data' => [
                'consignment_id' => 'DL121224VS8TTJ', 'merchant_order_id' => $this->order->number, 'order_status' => 'Pending', 'delivery_fee' => 80,
            ]]),
        ]);

        $shipment = $this->book('pathao', ['weightKg' => 1.2]);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/orders') && $r->hasHeader('Authorization', 'Bearer tok')
            && $r['store_id'] === 55 && $r['item_weight'] === 1.2 && $r['amount_to_collect'] === 960 && $r['item_quantity'] === 2);
        $this->assertSame('DL121224VS8TTJ', $shipment->consignment_id);
        $this->assertSame(8000, $shipment->delivery_fee);

        // Webhook: Pathao signs with the shared secret and expects its integration header back.
        $this->postJson('/api/v1/webhooks/courier/pathao', ['consignment_id' => 'DL121224VS8TTJ', 'event' => 'order.delivered'], ['X-PATHAO-Signature' => 'test-webhook-token-123456'])
            ->assertStatus(202)
            ->assertHeader('X-Pathao-Merchant-Webhook-Integration-Secret');
        $this->assertSame(OrderStatus::Delivered, $this->order->fresh()->status);
    }

    public function test_redx_matches_the_thana_to_its_area(): void
    {
        Http::fake([
            '*/areas*' => Http::response(['areas' => [
                ['id' => 1, 'name' => 'Dhanmondi', 'post_code' => 1209],
                ['id' => 7, 'name' => 'Mirpur', 'post_code' => 1216],
            ]]),
            '*/parcel' => Http::response(['tracking_id' => '21A427TU4BN3R']),
        ]);

        $shipment = $this->book('redx');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/parcel') && $r['delivery_area_id'] === 7
            && $r['delivery_area'] === 'Mirpur' && $r['cash_collection_amount'] === '960' && $r->hasHeader('API-ACCESS-TOKEN', 'Bearer rx-token'));
        $this->assertSame('21A427TU4BN3R', $shipment->consignment_id);

        $this->postJson('/api/v1/webhooks/courier/redx?token=test-webhook-token-123456', ['tracking_number' => '21A427TU4BN3R', 'status' => 'delivered'])->assertOk();
        $this->assertSame(OrderStatus::Delivered, $this->order->fresh()->status);
    }

    public function test_sync_command_polls_unfinished_shipments(): void
    {
        Http::fake([
            '*/create_order' => Http::response(['status' => 200, 'consignment' => ['consignment_id' => 77, 'tracking_code' => 'X', 'status' => 'in_review']]),
            '*/status_by_cid/77' => Http::response(['status' => 200, 'delivery_status' => 'delivered_approval_pending']),
        ]);
        $this->book('steadfast');
        Shipment::query()->update(['synced_at' => now()->subHours(2)]);

        Artisan::call('courier:sync');

        $this->assertSame(OrderStatus::Delivered, $this->order->fresh()->status);
    }

    public function test_admin_screens_open_and_book_from_the_order_page(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
        Http::fake(['portal.packzy.com/*' => Http::response(['status' => 200, 'consignment' => ['consignment_id' => 3, 'tracking_code' => 'T3', 'status' => 'in_review']])]);

        $this->get('/admin/settings/courier')->assertOk()->assertDontSee('sf-secret');
        Livewire::test(ManageCourierSettings::class)->call('save')->assertHasNoErrors();
        $this->assertSame('sf-secret', app(CourierSettings::class)->steadfast_secret_key); // blank secret kept

        Livewire::test(ViewOrder::class, ['record' => $this->order->number])
            ->callAction('bookCourier', ['courier' => 'steadfast', 'cod_amount' => 500, 'weight' => 1])
            ->assertHasNoActionErrors();

        $this->assertSame(50000, Shipment::first()->cod_amount);
        $this->get("/admin/orders/{$this->order->number}")->assertOk()->assertSee('T3');
        $this->get('/admin/orders')->assertOk()->assertSee('Steadfast');
    }

    public function test_order_manager_cannot_open_courier_settings(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));

        $this->get('/admin/settings/courier')->assertForbidden();
    }
}

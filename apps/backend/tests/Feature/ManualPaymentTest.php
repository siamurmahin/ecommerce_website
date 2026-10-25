<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Actions\RecordPayment;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use App\Filament\Pages\ManagePaymentSettings;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\User;
use App\Settings\PaymentSettings;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
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
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 10, 'is_active' => true,
        ]);
    }

    private function settings(array $overrides = []): void
    {
        $settings = app(PaymentSettings::class);
        $settings->fill([
            'cod_enabled' => true,
            'cod_advance' => 'none',
            'wallets' => [
                'bkash' => ['enabled' => true, 'number' => '01711000000', 'type' => 'personal', 'instructions' => 'রেফারেন্সে ফোন নম্বর দিন'],
                'nagad' => ['enabled' => false, 'number' => null, 'type' => 'personal', 'instructions' => null],
                'rocket' => ['enabled' => true, 'number' => null, 'type' => 'agent', 'instructions' => null],
            ],
            ...$overrides,
        ])->save();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'রহিম', 'phone' => '01712345678',
            'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০',
            'payment_method' => 'cod',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            ...$overrides,
        ];
    }

    public function test_options_list_only_switched_on_methods_with_their_numbers(): void
    {
        $this->settings();

        $methods = $this->getJson('/api/v1/checkout/options')->assertOk()->assertJsonPath('cod_advance', 'none')->json('payment_methods');

        // Nagad is off and Rocket has no number, so only COD and bKash show.
        $this->assertSame(['cod', 'bkash'], array_column($methods, 'code'));
        $this->assertSame('01711000000', $methods[1]['number']);
        $this->assertSame('personal', $methods[1]['account_type']);
    }

    public function test_wallet_order_needs_sender_and_unique_transaction_id(): void
    {
        $this->settings();

        $this->postJson('/api/v1/orders', $this->payload(['payment_method' => 'bkash']))
            ->assertUnprocessable()->assertJsonValidationErrors(['payment_sender', 'payment_trx_id']);
        $this->postJson('/api/v1/orders', $this->payload(['payment_method' => 'nagad', 'payment_sender' => '01811111111', 'payment_trx_id' => 'ABC123XYZ']))
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $this->postJson('/api/v1/orders', $this->payload(['payment_method' => 'bkash', 'payment_sender' => '+880 1811-111111', 'payment_trx_id' => 'abc123xyz']))
            ->assertCreated()
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.advance_amount', 51000) // 450 + 60 Dhaka delivery
            ->assertJsonPath('data.due_amount', 0);

        $order = Order::first();
        $this->assertSame('ABC123XYZ', $order->payment_trx_id);
        $this->assertSame('01811111111', $order->payment_sender);

        $this->postJson('/api/v1/orders', $this->payload(['phone' => '01912345678', 'payment_method' => 'bkash', 'payment_sender' => '01811111111', 'payment_trx_id' => 'ABC123XYZ']))
            ->assertUnprocessable()->assertJsonValidationErrors('payment_trx_id');
    }

    public function test_cod_can_require_the_delivery_charge_in_advance(): void
    {
        $this->settings(['cod_advance' => 'delivery']);

        $this->postJson('/api/v1/orders', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('payment_wallet');

        $this->postJson('/api/v1/orders', $this->payload(['payment_wallet' => 'bkash', 'payment_sender' => '01811111111', 'payment_trx_id' => 'DEL123456']))
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.advance_amount', 6000)
            ->assertJsonPath('data.due_amount', 45000);

        $order = Order::first();
        app(RecordPayment::class)->approve($order, 6000);
        $this->assertSame(PaymentStatus::Partial, $order->fresh()->payment_status);
        $this->assertSame(45000, $order->fresh()->dueOnDelivery());
    }

    public function test_cod_can_be_switched_off(): void
    {
        $this->settings(['cod_enabled' => false]);

        $this->postJson('/api/v1/orders', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('payment_method');
    }

    public function test_staff_verify_or_reject_a_pending_payment(): void
    {
        $this->settings();
        $this->postJson('/api/v1/orders', $this->payload(['payment_method' => 'bkash', 'payment_sender' => '01811111111', 'payment_trx_id' => 'TRX998877']))->assertCreated();
        $order = Order::first();
        $this->actingAs($admin = User::factory()->create());
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('payment', ['decision' => 'reject', 'reason' => 'TrxID পাওয়া যায়নি'])
            ->assertHasNoActionErrors();
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertSame('TrxID পাওয়া যায়নি', $order->fresh()->payment_note);

        // Customer sends it again later; staff record it by hand.
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('payment', ['wallet' => 'bkash', 'amount' => 510, 'sender' => '01811111111', 'trx_id' => 'NEW445566'])
            ->assertHasNoActionErrors();
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(51000, $order->paid_amount);
        $this->assertSame(0, $order->dueOnDelivery());
        $this->assertSame($admin->id, $order->payment_verified_by);
    }

    public function test_settings_page_saves_wallet_numbers_and_refuses_advance_without_a_wallet(): void
    {
        $this->settings(['wallets' => array_map(fn ($w) => [...$w, 'enabled' => false], app(PaymentSettings::class)->wallets)]);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin);

        Livewire::test(ManagePaymentSettings::class)
            ->set('data.cod_advance', 'delivery')
            ->call('save')
            ->assertHasErrors('data.cod_advance');

        Livewire::test(ManagePaymentSettings::class)
            ->set('data.wallets.nagad.enabled', true)
            ->set('data.wallets.nagad.number', '+880 1999-000000')
            ->set('data.cod_advance', 'delivery')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(PaymentSettings::class)->refresh();
        $this->assertSame('01999000000', $settings->wallets['nagad']['number']);
        $this->assertTrue($settings->requiresDeliveryAdvance());
    }
}

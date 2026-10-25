<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use App\Domain\Sms\Models\SmsLog;
use App\Filament\Pages\ManageSmsSettings;
use App\Models\User;
use App\Settings\SmsSettings;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingSeeder::class);
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 5, 'is_active' => true,
        ]);
    }

    private function configure(array $values): void
    {
        app(SmsSettings::class)->fill($values)->save();
    }

    private function placeOrder(): Order
    {
        $number = $this->postJson('/api/v1/orders', [
            'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod',
            'items' => [['product_id' => Product::first()->id, 'quantity' => 2]],
        ])->assertCreated()->json('data.number');

        return Order::where('number', $number)->firstOrFail();
    }

    public function test_nothing_is_sent_while_sms_is_off(): void
    {
        $this->placeOrder();

        $this->assertSame(0, SmsLog::count());
    }

    public function test_new_order_texts_the_customer_and_admins_through_the_gateway(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 202])]);
        $this->configure(['enabled' => true, 'gateway' => 'bulksmsbd', 'api_key' => 'key-1', 'sender_id' => '8809617', 'admin_phones' => '01811111111, bad, +8801911111111']);

        $order = $this->placeOrder();

        $logs = SmsLog::orderBy('id')->get();
        $this->assertSame(['01712345678', '01811111111', '01911111111'], $logs->pluck('to')->all());
        $this->assertTrue($logs->every(fn (SmsLog $log) => $log->status === 'sent'));
        $this->assertStringContainsString($order->number, $logs[0]->message);
        $this->assertStringContainsString('৳960', $logs[0]->message); // 900 + 60 delivery

        Http::assertSent(fn (Request $request) => $request['number'] === '8801712345678' && $request['api_key'] === 'key-1');
    }

    public function test_status_change_uses_its_template_and_failures_are_logged(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 1007, 'error_message' => 'Balance insufficient'])]);
        $order = $this->placeOrder();
        $this->configure(['enabled' => true, 'gateway' => 'bulksmsbd', 'api_key' => 'k']);

        try {
            app(ChangeOrderStatus::class)->handle($order, OrderStatus::Confirmed);
        } catch (\RuntimeException) {
            // The sync queue rethrows after the job's failed() hook has run.
        }

        $log = SmsLog::sole();
        $this->assertSame('confirmed', $log->event);
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Balance insufficient', $log->error);

        app(ChangeOrderStatus::class)->handle($order->fresh(), OrderStatus::Processing); // no template for this status
        $this->assertSame(1, SmsLog::count());
    }

    public function test_admin_saves_templates_and_blank_key_keeps_the_old_one(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
        $this->configure(['api_key' => 'secret-key']);

        Livewire::test(ManageSmsSettings::class)
            ->assertSet('data.api_key', null)
            ->set('data.enabled', true)
            ->set('data.gateway', 'sslwireless')
            ->set('data.templates.shipped.body', 'পাঠানো হয়েছে {order}')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(SmsSettings::class)->refresh();
        $this->assertSame('sslwireless', $settings->gateway);
        $this->assertSame('secret-key', $settings->api_key);
        $this->assertSame('পাঠানো হয়েছে {order}', $settings->templates['shipped']['body']);
    }
}

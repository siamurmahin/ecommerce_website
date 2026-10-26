<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use App\Domain\WhatsApp\Models\WhatsAppMessage;
use App\Filament\Pages\ManageWhatsAppSettings;
use App\Models\User;
use App\Settings\WhatsAppSettings;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppTest extends TestCase
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

    private function placeOrder(): Order
    {
        $number = $this->postJson('/api/v1/orders', [
            'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod',
            'items' => [['product_id' => Product::first()->id, 'quantity' => 2]],
        ])->assertCreated()->json('data.number');

        return Order::where('number', $number)->firstOrFail();
    }

    public function test_nothing_is_sent_while_whatsapp_is_off(): void
    {
        $this->placeOrder();

        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_order_updates_go_out_as_approved_templates(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        app(WhatsAppSettings::class)->fill(['enabled' => true, 'gateway' => 'meta', 'phone_number_id' => '1234', 'access_token' => 'tok', 'admin_phones' => '01811111111'])->save();
        $templates = app(WhatsAppSettings::class)->templates;
        $templates['admin_new_order']['enabled'] = true;
        app(WhatsAppSettings::class)->fill(['templates' => $templates])->save();

        $order = $this->placeOrder();

        $messages = WhatsAppMessage::orderBy('id')->get();
        $this->assertSame(['01712345678', '01811111111'], $messages->pluck('to')->all());
        $this->assertSame(['রহিম', $order->number, '৳960'], $messages[0]->params); // {name} {order} {total}
        $this->assertStringContainsString($order->number, $messages[0]->preview);
        $this->assertTrue($messages->every(fn (WhatsAppMessage $m) => $m->status === 'sent'));

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/1234/messages')
            && $request->hasHeader('Authorization', 'Bearer tok')
            && $request['to'] === '8801712345678'
            && $request['template']['name'] === 'order_placed'
            && $request['template']['components'][0]['parameters'][1]['text'] === $order->number);

        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Confirmed);
        $this->assertSame('order_confirmed', WhatsAppMessage::latest('id')->first()->template);
    }

    public function test_failures_are_logged(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template does not exist']], 400)]);
        $order = $this->placeOrder();
        app(WhatsAppSettings::class)->fill(['enabled' => true, 'gateway' => 'meta', 'phone_number_id' => '1234', 'access_token' => 'tok'])->save();

        try {
            app(ChangeOrderStatus::class)->handle($order, OrderStatus::Confirmed);
        } catch (\RuntimeException) {
            // The sync queue rethrows after the job's failed() hook has run.
        }

        $message = WhatsAppMessage::sole();
        $this->assertSame('failed', $message->status);
        $this->assertStringContainsString('Template does not exist', $message->error);
    }

    public function test_admin_saves_settings_and_sees_the_log(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
        app(WhatsAppSettings::class)->fill(['access_token' => 'old-token'])->save();

        Livewire::test(ManageWhatsAppSettings::class)
            ->fillForm(['enabled' => true, 'gateway' => 'meta', 'phone_number_id' => '999', 'access_token' => ''])
            ->call('save')->assertHasNoErrors();

        $settings = app(WhatsAppSettings::class)->refresh();
        $this->assertSame(['999', 'old-token'], [$settings->phone_number_id, $settings->access_token]); // blank token kept

        Livewire::test(ManageWhatsAppSettings::class)->fillForm(['templates.confirmed.template' => 'Bad Name'])->call('save')
            ->assertHasErrors(['data.templates.confirmed.template']);

        $this->get('/admin/whatsapp-messages')->assertOk();
    }
}

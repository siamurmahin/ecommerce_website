<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Actions\PlaceOrder;
use App\Domain\Order\Enums\OrderSource;
use App\Domain\Shipping\Models\District;
use App\Filament\Pages\ManageNotificationSettings;
use App\Mail\NewOrderMail;
use App\Models\User;
use App\Settings\NotificationSettings;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Database\Seeders\ThanaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class NewOrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ShippingSeeder::class, ThanaSeeder::class, RoleSeeder::class]);
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 5, 'is_active' => true,
        ]);
        app(NotificationSettings::class)->fill(['new_order_email' => true, 'emails' => 'owner@example.com, bad-address, staff@example.com', 'admin_bell' => true])->save();
    }

    private function order(array $context = [])
    {
        $dhaka = District::where('name_en', 'Dhaka')->firstOrFail();

        return app(PlaceOrder::class)->handle([
            'name' => 'রহিম', 'phone' => '01712345678', 'address' => 'বাড়ি ১২',
            'district_id' => $dhaka->id, 'thana_id' => $dhaka->thanas()->value('id'),
            'payment_method' => 'cod', 'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ], $context);
    }

    public function test_website_order_emails_the_shop_and_rings_the_bell(): void
    {
        Mail::fake();
        $admin = tap(User::factory()->create())->assignRole('super_admin');
        $packer = tap(User::factory()->create())->assignRole('content_editor');

        $order = $this->order();

        Mail::assertQueued(NewOrderMail::class, fn (NewOrderMail $mail) => $mail->hasTo('owner@example.com') && $mail->hasTo('staff@example.com')
            && ! $mail->hasTo('bad-address') && $mail->order->is($order));
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertStringContainsString($order->number, $admin->notifications()->first()->data['title']);
        $this->assertSame(0, $packer->notifications()->count()); // can't see orders

        $html = (new NewOrderMail($order))->render();
        $this->assertStringContainsString('উদ আতর', $html);
        $this->assertStringContainsString('01712345678', $html);
        $this->assertStringContainsString("/admin/orders/{$order->number}", $html);
    }

    public function test_switches_and_staff_entered_orders_send_nothing(): void
    {
        Mail::fake();
        $admin = tap(User::factory()->create())->assignRole('super_admin');

        $this->order(['source' => OrderSource::Phone]);
        app(NotificationSettings::class)->fill(['new_order_email' => false, 'admin_bell' => false])->save();
        $this->order();

        Mail::assertNothingQueued();
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_settings_page_saves_and_sends_a_test_email(): void
    {
        Mail::fake();
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
        $this->order();

        $this->get('/admin/settings/notifications')->assertOk();
        Livewire::test(ManageNotificationSettings::class)
            ->set('data.emails', 'not-an-email')->call('save')->assertHasErrors()
            ->set('data.emails', 'me@example.com')->call('save')->assertHasNoErrors()
            ->call('sendTest');

        Mail::assertSent(NewOrderMail::class, fn (NewOrderMail $mail) => $mail->hasTo('me@example.com'));
    }

    public function test_order_manager_cannot_open_notification_settings(): void
    {
        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));

        $this->get('/admin/settings/notifications')->assertForbidden();
    }
}

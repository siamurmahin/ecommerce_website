<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Courier\Enums\ShipmentStatus;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Actions\PlaceOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use App\Filament\Pages\ManageNotificationSettings;
use App\Mail\CustomerOrderMail;
use App\Models\User;
use App\Settings\NotificationSettings;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Database\Seeders\ThanaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerOrderEmailTest extends TestCase
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
        app(NotificationSettings::class)->fill(['new_order_email' => false, 'reply_to' => 'support@example.com'])->save();
    }

    private function order(?string $email = 'rahim@example.com', string $locale = 'bn'): Order
    {
        $dhaka = District::where('name_en', 'Dhaka')->firstOrFail();

        return app(PlaceOrder::class)->handle([
            'name' => 'রহিম', 'phone' => '01712345678', 'email' => $email, 'address' => 'বাড়ি ১২',
            'district_id' => $dhaka->id, 'thana_id' => $dhaka->thanas()->value('id'),
            'payment_method' => 'cod', 'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ], ['locale' => $locale]);
    }

    public function test_customer_gets_a_confirmation_in_their_language(): void
    {
        Mail::fake();

        $order = $this->order(locale: 'en');

        Mail::assertQueued(CustomerOrderMail::class, fn (CustomerOrderMail $mail) => $mail->hasTo('rahim@example.com')
            && $mail->event === 'placed' && $mail->order->is($order));

        $mail = new CustomerOrderMail($order, 'placed');
        $mail->assertHasSubject("We received your order {$order->number}");
        $mail->assertHasReplyTo('support@example.com');
        $mail->assertSeeInHtml('উদ আতর');
        $mail->assertSeeInHtml("/en/order/{$order->number}?token={$order->access_token}", false);
        $mail->assertSeeInHtml('Cash on delivery');
    }

    public function test_status_updates_follow_the_chosen_events(): void
    {
        $order = $this->order();
        Mail::fake();
        app(NotificationSettings::class)->fill(['customer_email_events' => ['shipped', 'delivered']])->save();

        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Confirmed);
        $order->shipments()->create([
            'courier' => 'steadfast', 'consignment_id' => 'C-991', 'tracking_code' => 'TRK991',
            'raw_status' => 'in_review', 'status' => ShipmentStatus::Booked, 'cod_amount' => $order->total,
        ]);
        app(ChangeOrderStatus::class)->handle($order->refresh(), OrderStatus::Shipped);

        Mail::assertQueuedCount(1);
        Mail::assertQueued(CustomerOrderMail::class, fn (CustomerOrderMail $mail) => $mail->event === 'shipped');

        $mail = new CustomerOrderMail($order->refresh(), 'shipped');
        $mail->assertHasSubject("অর্ডার {$order->number} কুরিয়ারে দেওয়া হয়েছে");
        $mail->assertSeeInHtml('TRK991');
        $mail->assertSeeInHtml("/order/{$order->number}?token=", false);
    }

    public function test_nothing_goes_without_an_address_or_with_emails_off(): void
    {
        Mail::fake();

        $this->order(email: null);
        app(NotificationSettings::class)->fill(['customer_emails' => false])->save();
        app(ChangeOrderStatus::class)->handle($this->order(), OrderStatus::Delivered);

        Mail::assertNotQueued(CustomerOrderMail::class);
    }

    public function test_checkout_options_say_whether_to_ask_for_email(): void
    {
        $this->getJson('/api/v1/checkout/options')->assertOk()->assertJsonPath('ask_email', true);

        app(NotificationSettings::class)->fill(['ask_email_at_checkout' => false])->save();
        $this->getJson('/api/v1/checkout/options')->assertJsonPath('ask_email', false);
    }

    public function test_settings_page_saves_customer_email_choices(): void
    {
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));

        Livewire::test(ManageNotificationSettings::class)
            ->set('data.customer_email_events', ['placed', 'delivered'])
            ->set('data.reply_to', 'not-an-email')->call('save')->assertHasErrors()
            ->set('data.reply_to', 'help@example.com')->call('save')->assertHasNoErrors();

        $settings = app(NotificationSettings::class)->refresh();
        $this->assertSame(['placed', 'delivered'], $settings->customer_email_events);
        $this->assertSame('help@example.com', $settings->reply_to);
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Actions\PlaceOrder;
use App\Domain\Shipping\Models\District;
use App\Mail\LoginCodeMail;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeSmsGateway;
use Tests\TestCase;

class CustomerEmailLoginTest extends TestCase
{
    use RefreshDatabase;

    private Product $attar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingSeeder::class);
        config(['sms.gateways.log' => FakeSmsGateway::class]);
        FakeSmsGateway::$sent = [];
        Mail::fake();

        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->attar = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 10, 'is_active' => true,
        ]);
    }

    private function placeOrder(string $phone, ?Customer $customer = null)
    {
        return app(PlaceOrder::class)->handle([
            'name' => 'রহিম', 'phone' => $phone, 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod',
            'items' => [['product_id' => $this->attar->id, 'quantity' => 1]],
        ], ['customer' => $customer]);
    }

    private function emailCustomer(array $attributes): Customer
    {
        return tap(new Customer($attributes), fn (Customer $c) => $c->forceFill(['email_verified_at' => now()])->save());
    }

    private function emailedCode(): string
    {
        $code = null;
        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_email_code_creates_an_account_without_a_phone(): void
    {
        $this->postJson('/api/v1/customer/email-otp', ['email' => ' Rahim@Example.com '])
            ->assertOk()->assertJson(['resend_after' => 60, 'is_new' => true]);
        Mail::assertSent(LoginCodeMail::class, fn (LoginCodeMail $mail) => $mail->hasTo('rahim@example.com'));
        $code = $this->emailedCode();

        $this->postJson('/api/v1/customer/email-otp', ['email' => 'rahim@example.com'])->assertStatus(429);
        $this->postJson('/api/v1/customer/email-verify', ['email' => 'rahim@example.com', 'code' => '000000'])->assertJsonValidationErrors('code');

        $token = $this->postJson('/api/v1/customer/email-verify', ['email' => 'rahim@example.com', 'code' => $code, 'name' => 'রহিম উদ্দিন'])
            ->assertOk()->assertJsonPath('customer.phone', null)->assertJsonPath('customer.email_verified', true)->json('token');

        $this->withToken($token)->getJson('/api/v1/customer')->assertOk()->assertJsonPath('data.email', 'rahim@example.com');
        $this->assertSame(1, Customer::count());
    }

    public function test_an_unverified_profile_email_does_not_open_the_account(): void
    {
        // Someone typed this address on a phone account; it was never confirmed, so the email login makes a new account.
        $phoneAccount = Customer::create(['phone' => '01712345678', 'email' => 'rahim@example.com']);

        $this->postJson('/api/v1/customer/email-otp', ['email' => 'rahim@example.com'])->assertJsonPath('is_new', true);
        $this->postJson('/api/v1/customer/email-verify', ['email' => 'rahim@example.com', 'code' => $this->emailedCode()])
            ->assertOk()->assertJsonPath('customer.phone', null);

        $this->assertSame(2, Customer::count());
        $this->assertNull($phoneAccount->fresh()->email_verified_at);
    }

    public function test_email_only_account_sees_only_its_own_orders(): void
    {
        $customer = $this->emailCustomer(['email' => 'rahim@example.com']);
        $stranger = $this->placeOrder('01712345678'); // someone else's order on that number
        $mine = $this->placeOrder('01712345678', $customer);
        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/customer/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.number', $mine->number);
        $this->assertNotSame($stranger->number, $mine->number);
    }

    public function test_verifying_a_phone_merges_with_the_account_on_that_number(): void
    {
        $guestOrder = $this->placeOrder('01712345678'); // recorded a guest customer for the number
        $guest = Customer::firstWhere('phone', '01712345678');
        $emailAccount = $this->emailCustomer(['email' => 'rahim@example.com', 'name' => 'রহিম']);
        $signedIn = $this->placeOrder('01712345678', $emailAccount);
        $emailAccount->addresses()->create(['name' => 'রহিম', 'phone' => '01712345678', 'district_id' => District::value('id'), 'address' => 'বাড়ি ১২']);
        $token = $emailAccount->createToken('storefront', ['customer'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/customer/otp', ['phone' => '01712345678'])->assertOk();
        $newToken = $this->withToken($token)->postJson('/api/v1/customer/phone', ['phone' => '01712345678', 'code' => FakeSmsGateway::lastCode()])
            ->assertOk()->assertJsonPath('customer.id', $guest->id)->assertJsonPath('customer.phone', '01712345678')
            ->assertJsonPath('customer.email', 'rahim@example.com')->assertJsonPath('customer.email_verified', true)
            ->assertJsonCount(1, 'customer.addresses')->json('token');

        $this->assertNull(Customer::find($emailAccount->id));
        $this->assertSame($guest->id, $signedIn->fresh()->customer_id);
        $this->app['auth']->forgetGuards();
        $this->withToken($newToken)->getJson('/api/v1/customer/orders')->assertOk()->assertJsonCount(2, 'data');
        $this->assertNotNull($guestOrder);
    }

    public function test_phone_account_adds_an_email_to_sign_in_with(): void
    {
        $customer = Customer::create(['phone' => '01712345678']);
        $this->emailCustomer(['email' => 'taken@example.com']);
        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/customer/email/otp', ['email' => 'taken@example.com'])->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/customer/email/otp', ['email' => 'rahim@example.com'])->assertOk();
        $this->postJson('/api/v1/customer/email', ['email' => 'rahim@example.com', 'code' => $this->emailedCode()])
            ->assertOk()->assertJsonPath('data.email_verified', true);

        // Changing it in the profile drops the verification again.
        $this->putJson('/api/v1/customer', ['name' => 'রহিম', 'email' => 'other@example.com'])->assertJsonPath('data.email_verified', false);
    }
}

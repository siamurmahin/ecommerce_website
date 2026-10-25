<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Actions\PlaceOrder;
use App\Domain\Order\Enums\OrderSource;
use App\Domain\Shipping\Models\District;
use App\Domain\Sms\Models\SmsLog;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Fakes\FakeSmsGateway;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    private Product $attar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingSeeder::class);
        config(['sms.gateways.log' => FakeSmsGateway::class]);
        FakeSmsGateway::$sent = [];

        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->attar = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 10, 'is_active' => true,
        ]);
    }

    private function placeOrder(string $phone = '01712345678', array $context = [])
    {
        return app(PlaceOrder::class)->handle([
            'name' => 'রহিম', 'phone' => $phone, 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod',
            'items' => [['product_id' => $this->attar->id, 'quantity' => 1]],
        ], $context);
    }

    public function test_otp_sign_in_creates_the_account(): void
    {
        $this->postJson('/api/v1/customer/otp', ['phone' => '+880 1712-345678'])
            ->assertOk()->assertJsonPath('is_new', true)->assertJsonPath('resend_after', 60);
        $code = FakeSmsGateway::lastCode();
        $this->assertNotNull($code);
        $this->assertStringNotContainsString($code, SmsLog::first()->message); // staff can't read codes

        $this->postJson('/api/v1/customer/otp', ['phone' => '01712345678'])->assertStatus(429);
        $this->postJson('/api/v1/customer/verify', ['phone' => '01712345678', 'code' => '000000'])->assertJsonValidationErrors('code');

        $token = $this->postJson('/api/v1/customer/verify', ['phone' => '01712345678', 'code' => $code, 'name' => 'রহিম উদ্দিন'])
            ->assertOk()
            ->assertJsonPath('customer.name', 'রহিম উদ্দিন')
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/customer')->assertOk()->assertJsonPath('data.phone', '01712345678');
        // A code works once.
        $this->postJson('/api/v1/customer/verify', ['phone' => '01712345678', 'code' => $code])->assertJsonValidationErrors('code');

        $this->withToken($token)->postJson('/api/v1/customer/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/customer')->assertUnauthorized();
    }

    public function test_too_many_wrong_codes_burn_the_code(): void
    {
        $this->postJson('/api/v1/customer/otp', ['phone' => '01712345678'])->assertOk();
        $code = FakeSmsGateway::lastCode();

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/customer/verify', ['phone' => '01712345678', 'code' => '111111']);
        }

        $this->postJson('/api/v1/customer/verify', ['phone' => '01712345678', 'code' => $code])->assertJsonValidationErrors('code');
    }

    public function test_staff_tokens_cannot_use_customer_routes(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/customer')->assertUnauthorized();
    }

    public function test_orders_and_addresses(): void
    {
        $guest = $this->placeOrder(); // before signing up
        $customer = Customer::firstWhere('phone', '01712345678');
        $this->assertSame('রহিম', $customer->name); // recorded from the order
        $this->placeOrder('01812345678');
        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/customer/orders')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $guest->number)
            ->assertJsonPath('data.0.access_token', $guest->access_token);

        $dhaka = District::where('name_en', 'Dhaka')->value('id');
        $address = ['label' => 'বাসা', 'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => $dhaka, 'address' => 'বাড়ি ১২, রোড ৪, মিরপুর'];
        $first = $this->postJson('/api/v1/customer/addresses', $address)->assertCreated()->assertJsonPath('data.is_default', true)->json('data.id');
        $second = $this->postJson('/api/v1/customer/addresses', [...$address, 'label' => 'অফিস', 'is_default' => true])->json('data.id');

        $this->getJson('/api/v1/customer')->assertJsonPath('data.addresses.0.id', $second)->assertJsonPath('data.addresses.1.is_default', false);
        $this->deleteJson("/api/v1/customer/addresses/{$second}")->assertNoContent();
        $this->assertTrue($customer->addresses()->find($first)->is_default);

        $other = Customer::create(['phone' => '01912345678'])->addresses()->create([...$address, 'phone' => '01912345678']);
        $this->deleteJson("/api/v1/customer/addresses/{$other->id}")->assertNotFound();

        $this->putJson('/api/v1/customer', ['name' => 'রহিম উদ্দিন', 'email' => 'rahim@example.com'])->assertJsonPath('data.email', 'rahim@example.com');
    }

    public function test_blocked_numbers_cannot_order_online_or_sign_in(): void
    {
        $customer = Customer::create(['phone' => '01712345678']);
        $token = $customer->createToken('storefront')->plainTextToken;
        $customer->update(['is_blocked' => true]);

        $this->assertSame(0, $customer->tokens()->count());
        $this->postJson('/api/v1/customer/otp', ['phone' => '01712345678'])->assertJsonValidationErrors('phone');
        $this->postJson('/api/v1/orders', [
            'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod',
            'items' => [['product_id' => $this->attar->id, 'quantity' => 1]],
        ])->assertJsonValidationErrors('phone');

        // Staff can still take the order by phone.
        $this->assertNotNull($this->placeOrder(context: ['source' => OrderSource::Phone]));
        $this->withToken($token)->getJson('/api/v1/customer')->assertUnauthorized();
    }

    public function test_customer_admin_screens(): void
    {
        $this->seed(RoleSeeder::class);
        $order = $this->placeOrder();
        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));

        $this->get('/admin/customers')->assertOk()->assertSee('01712345678');
        $this->get('/admin/customers/'.Customer::first()->id)->assertOk();
        $this->get('/admin/reviews')->assertOk();
        Livewire::test(OrdersRelationManager::class, ['ownerRecord' => Customer::first(), 'pageClass' => ViewCustomer::class])
            ->assertCanSeeTableRecords([$order]);

        $this->actingAs(tap(User::factory()->create())->assignRole('warehouse'));
        $this->get('/admin/customers')->assertForbidden();
    }

    public function test_wishlist_merges_guest_items_and_follows_the_account(): void
    {
        $hidden = Product::create([
            'category_id' => $this->attar->category_id, 'name' => 'hidden', 'slug' => 'hidden',
            'type' => ProductType::Simple, 'price' => 1000, 'stock' => 1, 'is_active' => false,
        ]);
        $customer = Customer::create(['phone' => '01712345678']);
        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/customer/wishlist/sync', ['product_ids' => [$this->attar->id, $hidden->id, 999]])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->attar->id);
        $this->postJson('/api/v1/customer/wishlist/sync', ['product_ids' => []])->assertJsonCount(1, 'data');
        $this->putJson("/api/v1/customer/wishlist/{$hidden->id}")->assertNotFound();

        $this->deleteJson("/api/v1/customer/wishlist/{$this->attar->id}")->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/customer/wishlist/{$this->attar->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/customer/wishlist')->assertJsonPath('data.0.slug', 'oud-attar');
    }
}

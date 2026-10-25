<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Reward\PointLedger;
use App\Domain\Shipping\Models\District;
use App\Settings\RewardSettings;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RewardsTest extends TestCase
{
    use RefreshDatabase;

    private Product $attar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingSeeder::class);
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->attar = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 100000, 'stock' => 50, 'is_active' => true,
        ]);
        app(RewardSettings::class)->fill([
            'points_enabled' => true, 'earn_per_100' => 2, 'point_value' => 100, 'min_redeem' => 10, 'max_redeem_percent' => 20,
            'referral_enabled' => true, 'referrer_points' => 50, 'referee_discount' => 15000, 'referee_min_subtotal' => 50000,
        ])->save();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod',
            'items' => [['product_id' => $this->attar->id, 'quantity' => 1]],
            ...$overrides,
        ];
    }

    private function moveTo(Order $order, OrderStatus $to): void
    {
        app(ChangeOrderStatus::class)->handle($order->fresh(), $to);
    }

    public function test_points_are_earned_on_delivery_and_reversed_on_return(): void
    {
        $order = Order::firstWhere('number', $this->postJson('/api/v1/orders', $this->payload())->assertCreated()->json('data.number'));
        $customer = Customer::firstWhere('phone', '01712345678');
        $this->assertSame(0, $customer->fresh()->points_balance);

        $this->moveTo($order, OrderStatus::Confirmed);
        $this->moveTo($order, OrderStatus::Shipped);
        $this->moveTo($order, OrderStatus::Delivered);
        $this->assertSame(20, $customer->fresh()->points_balance); // ৳1000 × 2 per ৳100
        $this->moveTo($order, OrderStatus::Delivered); // no double credit

        $this->moveTo($order, OrderStatus::Returned);
        $this->assertSame(0, $customer->fresh()->points_balance);
    }

    public function test_signed_in_customer_spends_points_and_gets_them_back_on_cancel(): void
    {
        $customer = Customer::create(['phone' => '01712345678', 'points_balance' => 0]);
        app(PointLedger::class)->post($customer, 300, 'adjust');
        Sanctum::actingAs($customer->fresh());

        // Max 20% of ৳1000 = ৳200 = 200 points.
        $this->postJson('/api/v1/customer/orders', $this->payload(['redeem_points' => 250]))->assertUnprocessable()->assertJsonValidationErrors('redeem_points');
        $this->postJson('/api/v1/customer/orders', $this->payload(['redeem_points' => 200, 'phone' => '01812345678']))->assertJsonValidationErrors('redeem_points');

        $response = $this->postJson('/api/v1/customer/orders', $this->payload(['redeem_points' => 200]))
            ->assertCreated()
            ->assertJsonPath('data.total', 100000 + 6000 - 20000);
        $this->assertSame(100, $customer->fresh()->points_balance);

        $this->moveTo(Order::firstWhere('number', $response->json('data.number')), OrderStatus::Cancelled);
        $this->assertSame(300, $customer->fresh()->points_balance);

        // The guest route ignores points.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/orders', $this->payload(['redeem_points' => 200, 'event_id' => 'x2']))->assertCreated()->assertJsonPath('data.total', 106000);
    }

    public function test_referral_code_gives_first_order_discount_and_referrer_points(): void
    {
        $friend = Customer::create(['phone' => '01912345678']);
        $code = $friend->referralCode();

        $this->postJson('/api/v1/cart/quote', ['items' => [['product_id' => $this->attar->id, 'quantity' => 1]], 'coupon_code' => strtolower($code)])
            ->assertOk()->assertJsonPath('data.discount_total', 15000)->assertJsonPath('data.discounts.0.type', 'referral');

        $this->postJson('/api/v1/orders', $this->payload(['coupon_code' => $code, 'phone' => '01912345678']))
            ->assertUnprocessable()->assertJsonValidationErrors('coupon_code'); // own code

        $order = Order::firstWhere('number', $this->postJson('/api/v1/orders', $this->payload(['coupon_code' => $code]))->assertCreated()->assertJsonPath('data.discount', 15000)->json('data.number'));
        $this->assertSame($friend->id, $order->referrer_id);
        $this->assertSame($friend->id, Customer::firstWhere('phone', '01712345678')->referred_by_id);

        $this->postJson('/api/v1/orders', $this->payload(['coupon_code' => $code, 'event_id' => 'second']))
            ->assertJsonValidationErrors('coupon_code'); // only the first order

        $this->moveTo($order, OrderStatus::Delivered);
        $this->assertSame(50, $friend->fresh()->points_balance);
    }

    public function test_account_shows_rewards_and_history(): void
    {
        $customer = Customer::create(['phone' => '01712345678']);
        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/customer')
            ->assertJsonPath('data.rewards.points.balance', 0)
            ->assertJsonPath('data.rewards.referral.friend_discount', 15000);
        $this->assertNotNull($customer->fresh()->referral_code);
        $this->getJson('/api/v1/customer/points')->assertOk()->assertJsonCount(0, 'data');
    }
}

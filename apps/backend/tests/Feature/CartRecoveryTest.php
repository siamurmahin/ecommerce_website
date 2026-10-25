<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Actions\SendCartReminders;
use App\Domain\Order\Enums\IncompleteStatus;
use App\Domain\Order\Models\IncompleteOrder;
use App\Domain\Sms\Models\SmsLog;
use App\Settings\SmsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CartRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private Product $attar;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(now(config('shop.timezone'))->setTime(12, 0)); // inside sending hours

        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->attar = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 10, 'is_active' => true,
        ]);

        $sms = app(SmsSettings::class);
        $sms->fill(['enabled' => true, 'recovery_enabled' => true, 'recovery_delay_minutes' => 60, 'recovery_coupon' => 'BACK10'])->save();
    }

    private function draft(string $phone = '01712345678', int $minutesAgo = 90): IncompleteOrder
    {
        return IncompleteOrder::create([
            'draft_id' => (string) str()->uuid(), 'customer_name' => 'রহিম উদ্দিন', 'customer_phone' => $phone,
            'items' => [['product_id' => $this->attar->id, 'quantity' => 2]], 'subtotal' => 90000,
            'status' => IncompleteStatus::Open, 'last_activity_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_reminder_goes_once_with_a_link_that_restores_the_cart(): void
    {
        $draft = $this->draft();
        $this->draft('01812345678', 10); // too recent

        $this->assertSame(1, app(SendCartReminders::class)->handle());
        $this->assertSame(0, app(SendCartReminders::class)->handle());

        $log = SmsLog::sole();
        $draft->refresh();
        $this->assertStringContainsString('/r/'.$draft->recovery_token, $log->message);
        $this->assertStringContainsString('রহিম', $log->message);
        $this->assertStringContainsString('BACK10', $log->message);

        $this->getJson('/api/v1/checkout/recover/'.$draft->recovery_token)
            ->assertOk()
            ->assertJsonPath('phone', '01712345678')
            ->assertJsonPath('coupon', 'BACK10')
            ->assertJsonPath('lines.0.slug', 'oud-attar')
            ->assertJsonPath('lines.0.quantity', 2);
        $this->assertNotNull($draft->fresh()->recovered_at);
        $this->getJson('/api/v1/checkout/recover/wrong-token')->assertNotFound();
    }

    public function test_no_reminder_at_night_for_blocked_phones_or_twice_a_week(): void
    {
        $this->travelTo(now(config('shop.timezone'))->setTime(23, 0));
        $this->draft();
        $this->assertSame(0, app(SendCartReminders::class)->handle());

        $this->travelTo(now(config('shop.timezone'))->addDay()->setTime(10, 0));
        IncompleteOrder::query()->update(['last_activity_at' => now()->subHours(2)]);
        Customer::create(['phone' => '01712345678', 'is_blocked' => true]);
        $this->assertSame(0, app(SendCartReminders::class)->handle());

        $this->draft('01912345678');
        $this->assertSame(1, app(SendCartReminders::class)->handle());
        $this->draft('01912345678');
        $this->assertSame(0, app(SendCartReminders::class)->handle()); // already reminded this week
    }
}

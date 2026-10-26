<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockAlert;
use App\Domain\Sms\Models\SmsLog;
use App\Models\User;
use App\Settings\SmsSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAlertTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 0, 'is_active' => true,
        ]);
    }

    public function test_customers_leave_a_number_and_get_one_sms_when_it_is_back(): void
    {
        app(SmsSettings::class)->fill(['enabled' => true, 'gateway' => 'log'])->save();

        $this->postJson('/api/v1/products/oud-attar/stock-alerts', ['phone' => '+8801712345678'])->assertCreated();
        $this->postJson('/api/v1/products/oud-attar/stock-alerts', ['phone' => '01712345678'])->assertCreated(); // same request again
        $this->postJson('/api/v1/products/oud-attar/stock-alerts', ['phone' => '01812345678'])->assertCreated();
        $this->postJson('/api/v1/products/oud-attar/stock-alerts', ['phone' => '12345'])->assertJsonValidationErrors('phone');
        $this->postJson('/api/v1/products/oud-attar/stock-alerts', ['phone' => '01912345678', 'variant_id' => 999])->assertStatus(422);
        $this->assertSame(2, StockAlert::count());

        $this->product->update(['stock' => 5]);

        $this->assertSame(0, StockAlert::pending()->count());
        $sms = SmsLog::where('event', 'stock_alert')->orderBy('id')->get();
        $this->assertSame(['01712345678', '01812345678'], $sms->pluck('to')->all());
        $this->assertStringContainsString('উদ আতর', $sms[0]->message);
        $this->assertStringContainsString('/p/oud-attar', $sms[0]->message);

        $this->product->update(['stock' => 0]);
        $this->product->update(['stock' => 3]);
        $this->assertSame(2, SmsLog::where('event', 'stock_alert')->count()); // already told
    }

    public function test_requests_wait_while_sms_is_off_and_staff_see_them(): void
    {
        $this->postJson('/api/v1/products/oud-attar/stock-alerts', ['phone' => '01712345678'])->assertCreated();
        $this->product->update(['stock' => 5]);

        $this->assertSame(1, StockAlert::pending()->count());
        $this->assertSame(0, SmsLog::count());

        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
        $this->get('/admin/stock-alerts')->assertOk()->assertSee('01712345678')->assertSee('উদ আতর');
    }
}

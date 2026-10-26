<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Finance\Models\Expense;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Report\DateRange;
use App\Domain\Report\SalesReport;
use App\Filament\Pages\Reports;
use App\Filament\Widgets\ActionNeededWidget;
use App\Filament\Widgets\SalesStatsWidget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-25 12:00', 'Asia/Dhaka'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function order(string $localTime, int $total, OrderStatus $status = OrderStatus::Delivered, array $extra = []): Order
    {
        $order = Order::create([
            'access_token' => Str::random(40), 'status' => $status, 'customer_name' => 'রহিম', 'customer_phone' => $extra['phone'] ?? '01712345678',
            'address' => 'মিরপুর', 'district_name' => $extra['district'] ?? 'ঢাকা', 'subtotal' => $total, 'total' => $total,
            'utm' => $extra['utm'] ?? null,
        ]);
        $order->forceFill(['created_at' => CarbonImmutable::parse($localTime, 'Asia/Dhaka')->utc()])->save();
        $order->items()->create(['name' => $extra['product'] ?? 'উদ আতর', 'unit_price' => $total, 'quantity' => 1, 'line_total' => $total]);

        return $order;
    }

    public function test_days_follow_bangladesh_time_and_lost_orders_are_not_sales(): void
    {
        $this->order('2026-10-25 01:00', 50000); // 24 Oct 19:00 UTC — still "today" in Dhaka
        $this->order('2026-10-24 23:30', 30000, OrderStatus::Returned);
        $this->order('2026-10-24 10:00', 20000, OrderStatus::Cancelled, ['phone' => '01812345678']);
        $this->order('2026-10-23 10:00', 10000, OrderStatus::Pending, ['phone' => '01912345678', 'utm' => ['utm_source' => 'facebook', 'utm_campaign' => 'eid']]);

        $today = SalesReport::for(DateRange::from('today'))->summary();
        $this->assertSame(1, $today['orders']);
        $this->assertSame(50000, $today['revenue']);

        $week = SalesReport::for(DateRange::from('7d'));
        $summary = $week->summary();
        $this->assertSame(4, $summary['orders']);
        $this->assertSame(2, $summary['sales']);
        $this->assertSame(60000, $summary['revenue']);
        $this->assertSame(30000, $summary['average']);
        $this->assertSame(1, $summary['cancelled']);
        $this->assertSame(50.0, $summary['delivery_rate']); // 1 delivered, 1 returned
        $this->assertSame(3, $summary['new_customers']);

        $daily = collect($week->daily())->keyBy('date');
        $this->assertCount(7, $daily);
        $this->assertSame(['date' => '2026-10-24', 'orders' => 2, 'sales' => 0, 'revenue' => 0], $daily['2026-10-24']);
        $this->assertSame(50000, $daily['2026-10-25']['revenue']);

        $this->assertSame([['source' => 'facebook', 'campaign' => 'eid', 'orders' => 1, 'sales' => 1, 'revenue' => 10000]], array_values(array_filter($week->sources(), fn ($s) => $s['source'] === 'facebook')));
        $this->assertSame('উদ আতর', $week->products()[0]['name']);
        $this->assertSame(2, $week->products()[0]['quantity']);
    }

    public function test_reports_page_switches_report_and_exports_csv(): void
    {
        $this->order('2026-10-25 10:00', 50000, extra: ['product' => 'জায়নামাজ', 'district' => 'সিলেট']);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin);

        Livewire::test(Reports::class)
            ->assertCanSeeTableRecords(['r0'])
            ->set('filters.report', 'districts')
            ->assertSee('সিলেট')
            ->set('filters.report', 'products')
            ->assertSee('জায়নামাজ')
            ->callAction('export')
            ->assertFileDownloaded('report-products-20260926-20261025.csv');
    }

    public function test_dashboard_widgets_follow_role_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        $this->order('2026-10-25 10:00', 50000);

        $manager = User::factory()->create();
        $manager->assignRole('order_manager');
        $this->actingAs($manager)->get('/admin')->assertOk()
            ->assertSeeLivewire(ActionNeededWidget::class)
            ->assertDontSeeLivewire(SalesStatsWidget::class);
        Livewire::test(ActionNeededWidget::class)->assertSee('কনফার্ম বাকি')->assertSee('পেমেন্ট যাচাই');
        $this->assertFalse(SalesStatsWidget::canView());

        $marketer = User::factory()->create();
        $marketer->assignRole('marketer');
        $this->actingAs($marketer)->get('/admin')->assertOk();
        $this->assertTrue(SalesStatsWidget::canView());
        Livewire::test(SalesStatsWidget::class, ['pageFilters' => ['period' => 'today']])->assertSee('৳500');
    }

    public function test_profit_subtracts_product_cost_and_expenses(): void
    {
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud', 'type' => ProductType::Simple, 'price' => 50000, 'cost_price' => 30000, 'stock' => 10, 'is_active' => true]);

        $order = $this->order('2026-10-25 10:00', 106000);
        $order->update(['delivery_charge' => 6000]);
        $order->items()->delete();
        $order->items()->create(['product_id' => $product->id, 'name' => 'উদ আতর', 'unit_price' => 50000, 'quantity' => 2, 'line_total' => 100000]);
        $product->update(['cost_price' => 99999]); // a later price change leaves the order's cost alone

        $this->order('2026-10-24 10:00', 40000, OrderStatus::Cancelled); // not a sale, no cost
        Expense::create(['category' => 'ads', 'title' => 'ফেসবুক অ্যাড', 'amount' => 15000, 'spent_on' => '2026-10-25']);
        Expense::create(['category' => 'packaging', 'title' => 'বাক্স', 'amount' => 5000, 'spent_on' => '2026-10-20']);
        Expense::create(['category' => 'ads', 'title' => 'আগের মাস', 'amount' => 99999, 'spent_on' => '2026-08-01']);

        $profit = SalesReport::for(DateRange::from('7d'))->profit();
        $this->assertSame(100000, $profit['goods']); // order total without delivery
        $this->assertSame(60000, $profit['purchase']);
        $this->assertSame(40000, $profit['gross']);
        $this->assertSame(['ads' => 15000, 'packaging' => 5000], $profit['expenses']);
        $this->assertSame(20000, $profit['net']);
        $this->assertSame(0, $profit['uncosted']);

        $row = collect(SalesReport::for(DateRange::from('7d'))->products())->firstWhere('product_id', $product->id);
        $this->assertSame([60000, 40000], [$row['cost'], $row['profit']]);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin);
        Livewire::test(Reports::class)->set('filters.report', 'profit')->set('filters.period', '7d')
            ->assertSee('গ্রস প্রফিট')->assertSee('৳400')->assertSee('বিজ্ঞাপন')->assertSee('-৳150')->assertSee('৳200');
        $this->get('/admin/expenses')->assertOk()->assertSee('ফেসবুক অ্যাড');
    }

    public function test_items_without_cost_price_are_flagged(): void
    {
        $this->order('2026-10-25 10:00', 50000);

        $this->assertSame(1, SalesReport::for(DateRange::from('7d'))->profit()['uncosted']);
        $this->assertNull(SalesReport::for(DateRange::from('7d'))->products()[0]['cost']);
    }

    public function test_campaign_roas_uses_tagged_ad_spend(): void
    {
        $this->order('2026-10-24 10:00', 60000, extra: ['utm' => ['utm_campaign' => 'eid']]);
        $this->order('2026-10-24 11:00', 40000, OrderStatus::Cancelled, ['utm' => ['utm_campaign' => 'eid']]);
        $this->order('2026-10-24 12:00', 30000); // no campaign
        Expense::create(['category' => 'ads', 'title' => 'FB', 'campaign' => 'eid', 'amount' => 20000, 'spent_on' => '2026-10-24']);
        Expense::create(['category' => 'ads', 'title' => 'Boost', 'amount' => 5000, 'spent_on' => '2026-10-24']);
        Expense::create(['category' => 'rent', 'title' => 'ভাড়া', 'campaign' => 'eid', 'amount' => 99900, 'spent_on' => '2026-10-24']);

        $rows = collect(SalesReport::for(DateRange::from('7d'))->campaigns())->keyBy('campaign');

        $this->assertSame(['orders' => 2, 'sales' => 1, 'revenue' => 60000, 'spend' => 20000, 'roas' => '3.00x', 'profit' => 40000],
            collect($rows['eid'])->only(['orders', 'sales', 'revenue', 'spend', 'roas', 'profit'])->all());
        $this->assertSame(5000, $rows['ক্যাম্পেইন ছাড়া বিজ্ঞাপন খরচ']['spend']);
        $this->assertCount(2, $rows);
    }

    public function test_variant_cost_price_wins_over_the_product(): void
    {
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'আতর', 'slug' => 'attar', 'type' => ProductType::Variable, 'price' => 50000, 'cost_price' => 20000, 'stock' => 0]);
        $big = $product->variants()->create(['price' => 80000, 'cost_price' => 35000, 'stock' => 5]);
        $small = $product->variants()->create(['price' => 50000, 'stock' => 5]);
        $order = $this->order('2026-10-24 10:00', 130000);

        $this->assertSame(35000, $order->items()->create(['product_id' => $product->id, 'product_variant_id' => $big->id, 'name' => 'আতর', 'unit_price' => 80000, 'quantity' => 1, 'line_total' => 80000])->unit_cost);
        $this->assertSame(20000, $order->items()->create(['product_id' => $product->id, 'product_variant_id' => $small->id, 'name' => 'আতর', 'unit_price' => 50000, 'quantity' => 1, 'line_total' => 50000])->unit_cost);
    }

    public function test_staff_report_counts_who_confirmed_what(): void
    {
        $rahim = User::factory()->create(['name' => 'রহিম স্টাফ']);
        $ok = $this->order('2026-10-24 10:00', 50000);
        $bad = $this->order('2026-10-24 11:00', 50000, OrderStatus::Returned);
        foreach ([$ok, $bad] as $order) {
            $order->statusHistories()->create(['from' => 'pending', 'to' => 'confirmed', 'user_id' => $rahim->id]);
        }
        $ok->statusHistories()->create(['from' => 'shipped', 'to' => 'delivered']);

        $rows = collect(SalesReport::for(DateRange::from('7d'))->staff())->keyBy('staff');

        $this->assertSame(['confirmed' => 2, 'delivered' => 1, 'lost' => 1, 'success' => '50%'], collect($rows['রহিম স্টাফ'])->only(['confirmed', 'delivered', 'lost', 'success'])->all());
        $this->assertSame(1, $rows['সিস্টেম / কুরিয়ার (স্বয়ংক্রিয়)']['changes']);
    }
}

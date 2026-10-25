<?php

namespace Tests\Feature;

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
}

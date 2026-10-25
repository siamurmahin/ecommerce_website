<?php

namespace App\Filament\Widgets;

use App\Domain\Report\SalesReport;
use App\Filament\Widgets\Concerns\UsesReportRange;
use App\Support\Money;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Headline sales numbers for the chosen period, compared with the period before it. */
class SalesStatsWidget extends StatsOverviewWidget
{
    use HasWidgetShield, UsesReportRange;

    protected static ?int $sort = 2;

    protected function getHeading(): ?string
    {
        return 'বিক্রি · '.$this->range()->label();
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $report = $this->report();
        $now = $report->summary();
        $before = SalesReport::for($this->range()->previous())->summary();
        $daily = collect($report->daily());

        return [
            $this->compare(Stat::make('বিক্রি', Money::format($now['revenue'])), $now['revenue'], $before['revenue'])
                ->chart($daily->pluck('revenue')->all()),
            $this->compare(Stat::make('অর্ডার', number_format($now['orders'])), $now['orders'], $before['orders'])
                ->chart($daily->pluck('orders')->all()),
            $this->compare(Stat::make('গড় অর্ডার মূল্য', Money::format($now['average'])), $now['average'], $before['average']),
            Stat::make('ডেলিভারি সফলতা', $now['delivery_rate'] === null ? '—' : $now['delivery_rate'].'%')
                ->description("ডেলিভারি {$now['delivered']} · রিটার্ন {$now['returned']}")
                ->color(match (true) {
                    $now['delivery_rate'] === null => 'gray',
                    $now['delivery_rate'] >= 85 => 'success',
                    $now['delivery_rate'] >= 70 => 'warning',
                    default => 'danger',
                }),
            $this->compare(Stat::make('নতুন কাস্টমার', number_format($now['new_customers'])), $now['new_customers'], $before['new_customers']),
            Stat::make('বাতিল', number_format($now['cancelled']))
                ->description($now['orders'] ? round($now['cancelled'] / $now['orders'] * 100).'% অর্ডার' : 'কোনো অর্ডার নেই')
                ->color($now['orders'] && $now['cancelled'] / $now['orders'] > 0.2 ? 'danger' : 'gray'),
        ];
    }

    /** "+12% আগের তুলনায়" with an arrow; nothing to compare against shows no trend. */
    private function compare(Stat $stat, int $now, int $before): Stat
    {
        if ($before === 0) {
            return $stat->description($now > 0 ? 'আগের সময়ে ছিল না' : null);
        }
        $change = round(($now - $before) / $before * 100);

        return $stat
            ->description(($change >= 0 ? '+' : '').$change.'% আগের সময়ের তুলনায়')
            ->descriptionIcon($change >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
            ->color($change >= 0 ? 'success' : 'danger');
    }
}

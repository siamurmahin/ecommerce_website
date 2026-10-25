<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesReportRange;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/** Orders (bars) and sales in taka (line) per day. */
class SalesChartWidget extends ChartWidget
{
    use HasWidgetShield, UsesReportRange;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 2];

    protected ?string $heading = 'দিনভিত্তিক বিক্রি';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $daily = collect($this->report()->daily());

        return [
            'labels' => $daily->map(fn ($d) => CarbonImmutable::parse($d['date'])->format('d M'))->all(),
            'datasets' => [
                [
                    'type' => 'line',
                    'label' => 'বিক্রি (৳)',
                    'data' => $daily->map(fn ($d) => round($d['revenue'] / 100))->all(),
                    'borderColor' => '#0F4C3A',
                    'backgroundColor' => '#0F4C3A',
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'অর্ডার',
                    'data' => $daily->pluck('orders')->all(),
                    'backgroundColor' => 'rgba(184, 151, 90, 0.45)',
                    'borderRadius' => 4,
                    'yAxisID' => 'y1',
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['position' => 'left', 'beginAtZero' => true],
                'y1' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false], 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}

<?php

namespace App\Filament\Widgets;

use App\Domain\Order\Enums\OrderStatus;
use App\Filament\Widgets\Concerns\UsesReportRange;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

/** Where this period's orders stand now. */
class OrderStatusChartWidget extends ChartWidget
{
    use HasWidgetShield, UsesReportRange;

    protected static ?int $sort = 4;

    protected ?string $heading = 'অর্ডারের অবস্থা';

    protected ?string $maxHeight = '300px';

    private const COLORS = [
        'pending' => '#B8975A', 'confirmed' => '#3B82F6', 'processing' => '#6366F1', 'on_hold' => '#A3A3A3',
        'shipped' => '#0EA5E9', 'delivered' => '#0F4C3A', 'cancelled' => '#DC2626', 'returned' => '#F97316',
    ];

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = $this->report()->statuses();
        $statuses = array_values(array_filter(OrderStatus::cases(), fn (OrderStatus $s) => ($counts[$s->value] ?? 0) > 0));

        return [
            'labels' => array_map(fn (OrderStatus $s) => $s->getLabel(), $statuses),
            'datasets' => [[
                'data' => array_map(fn (OrderStatus $s) => $counts[$s->value], $statuses),
                'backgroundColor' => array_map(fn (OrderStatus $s) => self::COLORS[$s->value], $statuses),
                'borderWidth' => 0,
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['display' => false], 'y' => ['display' => false]], 'plugins' => ['legend' => ['position' => 'bottom']]];
    }
}

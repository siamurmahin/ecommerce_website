<?php

namespace App\Filament\Widgets\Concerns;

use App\Domain\Report\DateRange;
use App\Domain\Report\SalesReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Reads the dashboard's period filter (see App\Filament\Pages\Dashboard). */
trait UsesReportRange
{
    use InteractsWithPageFilters;

    protected function range(): DateRange
    {
        return DateRange::from($this->pageFilters['period'] ?? '30d', $this->pageFilters['from'] ?? null, $this->pageFilters['until'] ?? null);
    }

    protected function report(): SalesReport
    {
        return SalesReport::for($this->range());
    }
}

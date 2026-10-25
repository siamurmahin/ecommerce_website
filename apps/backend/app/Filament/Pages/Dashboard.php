<?php

namespace App\Filament\Pages;

use App\Domain\Report\DateRange;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/** Home of the admin: what needs doing now, then sales for the chosen period. */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'ড্যাশবোর্ড';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')->label('সময়')->options(DateRange::PRESETS)->default('30d')->selectablePlaceholder(false)->native(false)->live(),
            DatePicker::make('from')->label('শুরু')->native(false)->maxDate(now(DateRange::timezone()))
                ->visible(fn (Get $get) => $get('period') === 'custom'),
            DatePicker::make('until')->label('শেষ')->native(false)->maxDate(now(DateRange::timezone()))
                ->visible(fn (Get $get) => $get('period') === 'custom'),
        ]);
    }

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 3];
    }
}

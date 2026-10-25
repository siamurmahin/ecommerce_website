<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesReportRange;
use App\Support\Money;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopProductsWidget extends TableWidget
{
    use HasWidgetShield, UsesReportRange;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 2];

    public function table(Table $table): Table
    {
        return $table
            ->heading('সেরা বিক্রিত পণ্য')
            ->records(fn () => collect($this->report()->products(8))->keyBy(fn ($row, $i) => $row['product_id'] ?? "deleted-{$i}")->all())
            ->columns([
                TextColumn::make('name')->label('পণ্য')->wrap(),
                TextColumn::make('quantity')->label('পিস')->numeric()->alignEnd(),
                TextColumn::make('revenue')->label('বিক্রি')->formatStateUsing(fn ($state) => Money::format((int) $state))->alignEnd(),
            ])
            ->paginated(false)
            ->emptyStateHeading('এই সময়ে কোনো বিক্রি নেই');
    }
}

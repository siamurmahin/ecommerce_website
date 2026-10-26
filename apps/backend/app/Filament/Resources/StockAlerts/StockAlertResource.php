<?php

namespace App\Filament\Resources\StockAlerts;

use App\Domain\Inventory\Models\StockAlert;
use App\Filament\Resources\StockAlerts\Pages\ListStockAlerts;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Customers waiting for an out-of-stock product — also a quick read on what to restock first. */
class StockAlertResource extends Resource
{
    protected static ?string $model = StockAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'ক্যাটালগ';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'স্টকে এলে জানান';

    protected static ?string $modelLabel = 'অনুরোধ';

    protected static ?string $pluralModelLabel = 'স্টকে এলে জানান';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = StockAlert::pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'variant.attributeValues.attribute']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('সময়')->since()->sortable(),
                TextColumn::make('product.name')->label('পণ্য')->limit(40)->searchable()
                    ->description(fn (StockAlert $record) => $record->variant?->label()),
                TextColumn::make('phone')->label('মোবাইল')->searchable()->url(fn (StockAlert $record) => 'tel:'.$record->phone),
                TextColumn::make('notified_at')->label('অবস্থা')->badge()
                    ->state(fn (StockAlert $record) => $record->notified_at ? 'জানানো হয়েছে' : 'অপেক্ষায়')
                    ->color(fn (StockAlert $record) => $record->notified_at ? 'success' : 'warning')
                    ->tooltip(fn (StockAlert $record) => $record->notified_at?->format('d M Y, h:i A')),
            ])
            ->filters([
                TernaryFilter::make('notified_at')->label('অবস্থা')->nullable()->trueLabel('জানানো হয়েছে')->falseLabel('অপেক্ষায়')->default(false),
                SelectFilter::make('product_id')->label('পণ্য')->relationship('product', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('markNotified')->label('জানানো হয়েছে')->icon(Heroicon::OutlinedCheck)->color('gray')
                    ->visible(fn (StockAlert $record) => ! $record->notified_at)
                    ->authorize('update')
                    ->action(fn (StockAlert $record) => $record->update(['notified_at' => now()])),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockAlerts::route('/')];
    }
}

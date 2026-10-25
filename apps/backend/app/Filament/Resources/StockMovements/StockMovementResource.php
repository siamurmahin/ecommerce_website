<?php

namespace App\Filament\Resources\StockMovements;

use App\Domain\Inventory\Enums\StockReason;
use App\Domain\Inventory\Models\StockMovement;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Read-only log of every stock change: sales, cancellations, purchases and manual edits. */
class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'ক্যাটালগ';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'স্টক মুভমেন্ট';

    protected static ?string $pluralModelLabel = 'স্টকের হিসাব';

    public static function table(Table $table): Table
    {
        return self::columns($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'variant.attributeValues.attribute', 'order', 'purchaseOrder', 'user']))
            ->filters([
                SelectFilter::make('reason')->label('কারণ')->options(StockReason::class)->multiple(),
                SelectFilter::make('product_id')->label('পণ্য')->relationship('product', 'name')->searchable()->preload(),
                Filter::make('date')->schema([
                    DatePicker::make('from')->label('থেকে'),
                    DatePicker::make('until')->label('পর্যন্ত'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->where('created_at', '<', now()->parse($d)->addDay()))),
            ]);
    }

    /** Shared with the product page's stock tab. */
    public static function columns(Table $table, bool $withProduct = true): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns(array_values(array_filter([
                TextColumn::make('created_at')->label('সময়')->dateTime('d M Y, h:i A')->sortable(),
                $withProduct ? TextColumn::make('product.name')->label('পণ্য')->limit(35)->searchable()
                    ->description(fn (StockMovement $record) => $record->variant?->label()) : null,
                ! $withProduct ? TextColumn::make('variant_label')->label('ভ্যারিয়েন্ট')->placeholder('—')->state(fn (StockMovement $record) => $record->variant?->label()) : null,
                TextColumn::make('change')->label('পরিবর্তন')->numeric()
                    ->formatStateUsing(fn (int $state) => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('balance')->label('পরে স্টক')->numeric(),
                TextColumn::make('reason')->label('কারণ')->badge(),
                TextColumn::make('note')->label('রেফারেন্স')->placeholder('—')
                    ->url(fn (StockMovement $record) => $record->order_id && $record->order
                        ? route('filament.admin.resources.orders.view', $record->order)
                        : ($record->purchase_order_id ? route('filament.admin.resources.purchase-orders.edit', $record->purchase_order_id) : null)),
                TextColumn::make('user.name')->label('যিনি করেছেন')->placeholder('সিস্টেম')->toggleable(),
            ])));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListStockMovements::route('/')];
    }
}

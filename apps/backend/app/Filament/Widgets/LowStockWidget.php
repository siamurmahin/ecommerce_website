<?php

namespace App\Filament\Widgets;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Product;
use App\Filament\Resources\Products\ProductResource;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Active products at or below their low-stock threshold (any variant, for variable products). */
class LowStockWidget extends TableWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 6;

    /** @return Builder<Product> */
    public static function query(): Builder
    {
        return Product::query()->where('is_active', true)->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->where('type', ProductType::Simple)->whereColumn('stock', '<=', 'low_stock_threshold'))
            ->orWhereHas('variants', fn (Builder $v) => $v->where('is_active', true)->whereColumn('product_variants.stock', '<=', 'products.low_stock_threshold')));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('কম স্টক')
            ->query(fn () => static::query()->with(['variants' => fn ($v) => $v->where('is_active', true)]))
            ->columns([
                TextColumn::make('name')->label('পণ্য')->wrap()
                    ->description(fn (Product $record) => $record->type === ProductType::Simple ? null : $record->variants
                        ->filter(fn ($v) => $v->stock <= $record->low_stock_threshold)
                        ->map(fn ($v) => $v->label('bn').': '.$v->stock)->implode(', ')),
                TextColumn::make('stock')->label('স্টক')->numeric()->alignEnd()->color('danger')
                    ->state(fn (Product $record) => $record->type === ProductType::Simple ? $record->stock : $record->variants->min('stock')),
            ])
            ->recordUrl(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
            ->defaultSort('stock')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('সব পণ্যের স্টক ঠিক আছে');
    }
}

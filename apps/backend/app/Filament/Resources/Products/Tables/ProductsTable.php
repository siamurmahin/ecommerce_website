<?php

namespace App\Filament\Resources\Products\Tables;

use App\Domain\Catalog\Enums\ProductBadge;
use App\Domain\Catalog\Models\Product;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['images', 'variants', 'category']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->disk('public')
                    ->state(fn (Product $record) => $record->images->first()?->path)
                    ->imageSize(48),
                TextColumn::make('name')
                    ->label('পণ্য')
                    ->description(fn (Product $record) => $record->category?->name)
                    ->searchable(['name', 'name_en', 'sku'])
                    ->wrap(),
                TextColumn::make('price')
                    ->label('দাম')
                    ->state(fn (Product $record) => $record->displayPrice())
                    ->formatStateUsing(fn (Product $record, $state) => ($record->isVariable() ? 'থেকে ' : '').Money::format($state))
                    ->description(fn (Product $record) => $record->compare_price ? Money::format($record->compare_price) : null)
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('স্টক')
                    ->state(fn (Product $record) => $record->totalStock())
                    ->badge()
                    ->color(fn (Product $record, int $state) => match (true) {
                        $state === 0 => 'danger',
                        $state <= $record->low_stock_threshold => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('badge')->label('ব্যাজ')->badge()->placeholder('—'),
                ToggleColumn::make('is_active')->label('সক্রিয়'),
                TextColumn::make('updated_at')->label('আপডেট')->since()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('ক্যাটাগরি')->relationship('category', 'name')->searchable()->preload(),
                SelectFilter::make('brand_id')->label('ব্র্যান্ড')->relationship('brand', 'name'),
                SelectFilter::make('badge')->label('ব্যাজ')->options(ProductBadge::class),
                TernaryFilter::make('is_active')->label('সক্রিয়'),
                Filter::make('low_stock')
                    ->label('লো স্টক (সিম্পল)')
                    ->query(fn (Builder $query) => $query->whereColumn('stock', '<=', 'low_stock_threshold')->where('type', 'simple')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ReplicateAction::make()
                    ->label('কপি')
                    ->excludeAttributes(['slug', 'sku', 'sold_count'])
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['name'] .= ' (কপি)';
                        $data['slug'] = Str::slug($data['name_en'] ?: 'product').'-'.Str::lower(Str::random(5));
                        $data['is_active'] = false;

                        return $data;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

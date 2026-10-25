<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Support\StorefrontCache;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            // Drag-reordering is one mass update with no model events.
            ->afterReordering(fn () => StorefrontCache::revalidate('catalog'))
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->square()->imageSize(40),
                TextColumn::make('name')
                    ->label('নাম')
                    ->description(fn ($record) => $record->name_en)
                    ->searchable(['name', 'name_en']),
                TextColumn::make('parent.name')->label('প্যারেন্ট')->placeholder('মূল'),
                TextColumn::make('products_count')->label('পণ্য')->counts('products')->sortable(),
                ToggleColumn::make('is_featured')->label('হোমপেজে'),
                ToggleColumn::make('is_active')->label('সক্রিয়'),
            ])
            ->filters([
                SelectFilter::make('parent_id')->label('প্যারেন্ট')->relationship('parent', 'name'),
                TernaryFilter::make('is_active')->label('সক্রিয়'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

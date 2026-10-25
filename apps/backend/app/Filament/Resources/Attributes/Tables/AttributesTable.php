<?php

namespace App\Filament\Resources\Attributes\Tables;

use App\Support\StorefrontCache;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttributesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            // Drag-reordering is one mass update with no model events.
            ->afterReordering(fn () => StorefrontCache::revalidate('catalog'))
            ->columns([
                TextColumn::make('name')->label('নাম')->description(fn ($record) => $record->name_en)->searchable(),
                TextColumn::make('values.value')->label('ভ্যালু')->badge()->limitList(6),
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

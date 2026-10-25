<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Domain\Customer\Models\CustomerAddress;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'সেভ করা ঠিকানা';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('নাম')->placeholder('—')->description(fn (CustomerAddress $record) => "{$record->name} · {$record->phone}"),
                TextColumn::make('address')->label('ঠিকানা')->wrap()
                    ->description(fn (CustomerAddress $record) => collect([$record->thana?->name, $record->district?->name])->filter()->implode(', ')),
                IconColumn::make('is_default')->label('ডিফল্ট')->boolean(),
            ]);
    }
}

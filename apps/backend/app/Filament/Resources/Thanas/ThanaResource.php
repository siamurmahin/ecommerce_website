<?php

namespace App\Filament\Resources\Thanas;

use App\Domain\Shipping\Models\District;
use App\Domain\Shipping\Models\Thana;
use App\Filament\Resources\Thanas\Pages\ManageThanas;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ThanaResource extends Resource
{
    protected static ?string $model = Thana::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'থানা/উপজেলা';

    protected static ?string $pluralModelLabel = 'থানা/উপজেলা';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('district_id')->label('জেলা')->required()->searchable()
                    ->options(fn () => District::orderBy('name')->pluck('name', 'id')),
                Toggle::make('is_active')->label('চেকআউটে দেখাও')->default(true)->inline(false),
                TextInput::make('name')->label('নাম (বাংলা)')->required()->maxLength(100),
                TextInput::make('name_en')->label('নাম (English)')->maxLength(100),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('নাম')->searchable(['name', 'name_en'])->sortable()
                    ->description(fn (Thana $record) => $record->name_en),
                TextColumn::make('district.name')->label('জেলা')->sortable(),
                ToggleColumn::make('is_active')->label('চেকআউটে'),
            ])
            ->filters([
                SelectFilter::make('district_id')->label('জেলা')->relationship('district', 'name')->searchable()->preload(),
                TernaryFilter::make('is_active')->label('চেকআউটে'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageThanas::route('/')];
    }
}

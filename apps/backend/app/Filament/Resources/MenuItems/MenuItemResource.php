<?php

namespace App\Filament\Resources\MenuItems;

use App\Domain\Content\Enums\MenuLocation;
use App\Domain\Content\Models\MenuItem;
use App\Filament\Resources\MenuItems\Pages\ManageMenuItems;
use App\Support\StorefrontCache;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|UnitEnum|null $navigationGroup = 'কনটেন্ট';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'মেনু আইটেম';

    protected static ?string $pluralModelLabel = 'মেনু';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('location')->label('কোথায় দেখাবে')->options(MenuLocation::class)->default(MenuLocation::Header)->required(),
                Grid::make(2)->schema([
                    TextInput::make('label')->label('নাম (বাংলা)')->required()->maxLength(60),
                    TextInput::make('label_en')->label('নাম (English)')->maxLength(60),
                ]),
                TextInput::make('url')->label('লিংক')->helperText('যেমন /shop?sort=popular, /c/attar বা পুরো URL')->required()->maxLength(255),
                Grid::make(2)->schema([
                    Toggle::make('is_highlighted')->label('হাইলাইট (অফারের মতো রঙিন)'),
                    Toggle::make('is_active')->label('সক্রিয়')->default(true),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            // Drag-reordering is one mass update with no model events.
            ->afterReordering(fn () => StorefrontCache::revalidate('menus'))
            ->columns([
                TextColumn::make('label')->label('নাম')->description(fn (MenuItem $record) => $record->url)->searchable(['label', 'label_en']),
                TextColumn::make('location')->label('জায়গা')->badge(),
                IconColumn::make('is_highlighted')->label('হাইলাইট')->boolean(),
                ToggleColumn::make('is_active')->label('সক্রিয়'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMenuItems::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\DeliveryZones;

use App\Domain\Shipping\Models\DeliveryZone;
use App\Domain\Shipping\Models\District;
use App\Filament\Resources\DeliveryZones\Pages\ManageDeliveryZones;
use App\Filament\Support\Fields;
use App\Support\Money;
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

class DeliveryZoneResource extends Resource
{
    protected static ?string $model = DeliveryZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'ডেলিভারি জোন';

    protected static ?string $pluralModelLabel = 'ডেলিভারি জোন ও চার্জ';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    TextInput::make('name')->label('নাম (বাংলা)')->required()->maxLength(100),
                    TextInput::make('name_en')->label('নাম (English)')->maxLength(100),
                    Fields::money('charge', 'ডেলিভারি চার্জ')->required(),
                    Grid::make(2)->schema([
                        TextInput::make('eta_min_days')->label('কমপক্ষে (দিন)')->numeric()->minValue(0)->required()->default(1),
                        TextInput::make('eta_max_days')->label('সর্বোচ্চ (দিন)')->numeric()->minValue(0)->required()->default(3)->gte('eta_min_days'),
                    ]),
                ]),
                Select::make('district_ids')
                    ->label('জেলা')
                    ->helperText('যে জেলা কোনো জোনে নেই, সেটা ডিফল্ট জোনের চার্জ পাবে।')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => District::orderBy('name')->pluck('name', 'id'))
                    ->afterStateHydrated(fn (Select $component, ?DeliveryZone $record) => $component->state($record?->districts()->pluck('id')->all() ?? []))
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(function (DeliveryZone $record, ?array $state) {
                        District::where('delivery_zone_id', $record->id)->whereNotIn('id', $state ?? [])->update(['delivery_zone_id' => null]);
                        District::whereIn('id', $state ?? [])->update(['delivery_zone_id' => $record->id]);
                    }),
                Grid::make(2)->schema([
                    Toggle::make('is_default')->label('ডিফল্ট জোন')->helperText('বাকি সব জেলার জন্য'),
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
            ->afterReordering(fn () => StorefrontCache::revalidate('shipping'))
            ->columns([
                TextColumn::make('name')->label('জোন')->description(fn (DeliveryZone $record) => $record->name_en),
                TextColumn::make('charge')->label('চার্জ')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('eta_min_days')->label('সময়')
                    ->formatStateUsing(fn (DeliveryZone $record) => "{$record->eta_min_days}–{$record->eta_max_days} দিন"),
                TextColumn::make('districts_count')->label('জেলা')->counts('districts')
                    ->formatStateUsing(fn ($state, DeliveryZone $record) => $record->is_default ? "{$state} + বাকি সব" : $state),
                IconColumn::make('is_default')->label('ডিফল্ট')->boolean(),
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
            'index' => ManageDeliveryZones::route('/'),
        ];
    }
}

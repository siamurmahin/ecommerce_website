<?php

namespace App\Filament\Resources\Bundles;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Enums\DiscountType;
use App\Domain\Marketing\Models\Bundle;
use App\Filament\Resources\Bundles\Pages\ManageBundles;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class BundleResource extends Resource
{
    protected static ?string $model = Bundle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static string|UnitEnum|null $navigationGroup = 'মার্কেটিং';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'বান্ডেল';

    protected static ?string $pluralModelLabel = 'বান্ডেল (একসাথে কিনুন)';

    public static function form(Schema $schema): Schema
    {
        $isPercent = fn (Get $get) => ($get('discount_type') instanceof DiscountType ? $get('discount_type') : DiscountType::tryFrom((string) $get('discount_type'))) !== DiscountType::Amount;

        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')->label('নাম (বাংলা)')->required()->maxLength(100)->placeholder('জুমার সেট'),
                TextInput::make('name_en')->label('নাম (English)')->maxLength(100),
                Select::make('discount_type')->label('ছাড়ের ধরন')->options(DiscountType::class)->required()->native(false)->live()->default(DiscountType::Percent),
                TextInput::make('discount_value')->label('পুরো সেটে ছাড়')->numeric()->required()->minValue(1)
                    ->suffix(fn (Get $get) => $isPercent($get) ? '%' : '৳')
                    ->maxValue(fn (Get $get) => $isPercent($get) ? 100 : null)
                    ->formatStateUsing(fn ($state, Get $get) => $state === null ? null : ($isPercent($get) ? $state : $state / 100))
                    ->dehydrateStateUsing(fn ($state, Get $get) => $isPercent($get) ? (int) $state : (int) round($state * 100)),
            ]),
            Repeater::make('items')->label('সেটের পণ্য')->relationship()->orderColumn('sort_order')
                ->columns(3)->minItems(2)->maxItems(6)->defaultItems(2)->addActionLabel('পণ্য যোগ করুন')
                ->helperText('সব পণ্য কার্টে থাকলে ছাড় নিজে থেকে বসবে। ভ্যারিয়েবল পণ্যের যেকোনো ভ্যারিয়েন্ট চলবে।')
                ->schema([
                    Select::make('product_id')->label('পণ্য')->required()->searchable()->columnSpan(2)
                        ->options(fn () => Product::orderBy('name')->pluck('name', 'id'))
                        ->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                    TextInput::make('quantity')->label('পরিমাণ')->numeric()->integer()->minValue(1)->maxValue(10)->default(1)->required(),
                ]),
            Toggle::make('is_active')->label('চালু')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->with('items.product'))
            ->columns([
                TextColumn::make('name')->label('নাম')->weight('bold')->searchable()
                    ->description(fn (Bundle $record) => $record->items->map(fn ($i) => ($i->product?->name ?? '?').($i->quantity > 1 ? " ×{$i->quantity}" : ''))->implode(' + ')),
                TextColumn::make('discount_value')->label('ছাড়')
                    ->formatStateUsing(fn (Bundle $record) => $record->discount_type === DiscountType::Percent ? "{$record->discount_value}%" : '৳'.number_format($record->discount_value / 100)),
                ToggleColumn::make('is_active')->label('চালু'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBundles::route('/')];
    }
}

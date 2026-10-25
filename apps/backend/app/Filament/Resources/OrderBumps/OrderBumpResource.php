<?php

namespace App\Filament\Resources\OrderBumps;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\OrderBump;
use App\Filament\Resources\OrderBumps\Pages\ManageOrderBumps;
use App\Filament\Support\Fields;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class OrderBumpResource extends Resource
{
    protected static ?string $model = OrderBump::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static string|UnitEnum|null $navigationGroup = 'মার্কেটিং';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'অর্ডার বাম্প';

    protected static ?string $pluralModelLabel = 'অর্ডার বাম্প';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('product_id')->label('পণ্য (শুধু সিম্পল পণ্য)')->required()->searchable()
                    ->options(fn () => Product::where('type', ProductType::Simple)->orderBy('name')->pluck('name', 'id')),
                Fields::money('price', 'বিশেষ দাম')->required()->helperText('চেকআউটে টিক দিলে এই দামে যোগ হবে'),
                TextInput::make('title')->label('শিরোনাম (বাংলা)')->required()->maxLength(120)->placeholder('হ্যাঁ, সাথে একটা আতরও দিন!'),
                TextInput::make('title_en')->label('শিরোনাম (English)')->maxLength(120),
                TextInput::make('description')->label('ছোট বিবরণ (বাংলা)')->maxLength(300),
                TextInput::make('description_en')->label('ছোট বিবরণ (English)')->maxLength(300),
            ]),
            Section::make('কখন দেখাবে')->columns(2)->schema([
                Fields::money('min_subtotal', 'কার্টে কমপক্ষে')->default(0),
                Select::make('trigger_product_ids')->label('শুধু এই পণ্যগুলো কার্টে থাকলে')->multiple()->searchable()
                    ->options(fn () => Product::orderBy('name')->pluck('name', 'id'))->helperText('খালি = সব কার্টে'),
            ]),
            Toggle::make('is_active')->label('চালু')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->with('product'))
            ->columns([
                TextColumn::make('title')->label('শিরোনাম')->weight('bold')->searchable()
                    ->description(fn (OrderBump $record) => $record->product?->name),
                TextColumn::make('price')->label('বিশেষ দাম')->formatStateUsing(fn ($state) => Money::format($state))
                    ->description(fn (OrderBump $record) => $record->product ? 'সাধারণ দাম '.Money::format($record->product->price) : null),
                ToggleColumn::make('is_active')->label('চালু'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOrderBumps::route('/')];
    }
}

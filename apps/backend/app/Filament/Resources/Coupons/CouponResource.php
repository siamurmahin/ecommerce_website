<?php

namespace App\Filament\Resources\Coupons;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Enums\CouponType;
use App\Domain\Marketing\Models\Coupon;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Filament\Support\Fields;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'মার্কেটিং';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $modelLabel = 'কুপন';

    protected static ?string $pluralModelLabel = 'কুপন';

    public static function form(Schema $schema): Schema
    {
        $isType = fn (string $type) => fn (Get $get) => ($get('type') instanceof CouponType ? $get('type')->value : $get('type')) === $type;

        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('code')->label('কোড')->required()->maxLength(40)->alphaDash()
                    ->unique(ignoreRecord: true)->default(fn () => strtoupper(Str::random(8)))
                    ->dehydrateStateUsing(fn (string $state) => strtoupper(trim($state)))
                    ->helperText('কাস্টমার যা লিখবে, যেমন EID20। ছোট/বড় হাতের পার্থক্য নেই।'),
                TextInput::make('description')->label('নোট (শুধু অ্যাডমিন দেখবে)')->maxLength(255),
                Select::make('type')->label('ধরন')->options(CouponType::class)->required()->native(false)->live()->default(CouponType::Percent),
                TextInput::make('value')->label('ছাড় (%)')->numeric()->integer()->minValue(1)->maxValue(100)->required()
                    ->visible($isType('percent')),
                Fields::money('value', 'ছাড় (টাকা)')->required()->visible($isType('amount')),
                Fields::money('max_discount', 'সর্বোচ্চ ছাড়')->visible($isType('percent'))->helperText('খালি = সীমা নেই'),
                Fields::money('min_subtotal', 'ন্যূনতম অর্ডার')->default(0),
            ]),
            Section::make('সময় ও সীমা')->columns(2)->schema([
                DateTimePicker::make('starts_at')->label('শুরু')->seconds(false)->native(false),
                DateTimePicker::make('ends_at')->label('শেষ')->seconds(false)->native(false)->after('starts_at'),
                TextInput::make('usage_limit')->label('মোট কতবার ব্যবহার করা যাবে')->numeric()->integer()->minValue(1)->helperText('খালি = যতবার খুশি'),
                TextInput::make('per_phone_limit')->label('একটা ফোন নম্বরে কতবার')->numeric()->integer()->minValue(1)->default(1),
            ]),
            Section::make('কোন পণ্যে চলবে')->description('দুটোই খালি রাখলে পুরো কার্টে চলবে।')->collapsible()->columns(2)->schema([
                Select::make('product_ids')->label('নির্দিষ্ট পণ্য')->multiple()->searchable()
                    ->options(fn () => Product::orderBy('name')->pluck('name', 'id')),
                Select::make('category_ids')->label('নির্দিষ্ট ক্যাটাগরি')->multiple()->searchable()
                    ->options(fn () => Category::orderBy('name')->pluck('name', 'id')),
            ]),
            Toggle::make('is_active')->label('চালু')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')->label('কোড')->weight('bold')->copyable()->searchable()
                    ->description(fn (Coupon $record) => $record->description),
                TextColumn::make('type')->label('ছাড়')
                    ->formatStateUsing(fn (Coupon $record) => match ($record->type) {
                        CouponType::Percent => "{$record->value}%".($record->max_discount ? ' (সর্বোচ্চ '.Money::format($record->max_discount).')' : ''),
                        CouponType::Amount => Money::format($record->value),
                        CouponType::FreeShipping => 'ফ্রি ডেলিভারি',
                    })
                    ->description(fn (Coupon $record) => $record->min_subtotal ? 'ন্যূনতম '.Money::format($record->min_subtotal) : null),
                TextColumn::make('used_count')->label('ব্যবহার')
                    ->formatStateUsing(fn (Coupon $record) => $record->used_count.($record->usage_limit ? " / {$record->usage_limit}" : '')),
                TextColumn::make('ends_at')->label('মেয়াদ')->dateTime('d M Y, h:i A')->placeholder('সীমা নেই')
                    ->color(fn (Coupon $record) => $record->ends_at?->isPast() ? 'danger' : null),
                ToggleColumn::make('is_active')->label('চালু'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCoupons::route('/')];
    }
}

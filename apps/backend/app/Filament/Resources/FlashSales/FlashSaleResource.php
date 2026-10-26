<?php

namespace App\Filament\Resources\FlashSales;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Enums\DiscountType;
use App\Domain\Marketing\Models\FlashSale;
use App\Filament\Resources\FlashSales\Pages\ManageFlashSales;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class FlashSaleResource extends Resource
{
    protected static ?string $model = FlashSale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'মার্কেটিং';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'ফ্ল্যাশ সেল';

    protected static ?string $pluralModelLabel = 'ফ্ল্যাশ সেল';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')->label('নাম (বাংলা)')->required()->maxLength(100)->placeholder('ঈদ ফ্ল্যাশ সেল'),
                TextInput::make('name_en')->label('নাম (English)')->maxLength(100),
                DateTimePicker::make('starts_at')->label('শুরু')->required()->seconds(false)->native(false)->default(now()->startOfHour()->addHour())
                    ->hintAction(Action::make('friday')->label('এই শুক্রবার সারাদিন')->icon(Heroicon::OutlinedCalendarDays)
                        ->action(function (Set $set) {
                            $friday = now(config('shop.timezone'))->isFriday() ? now(config('shop.timezone')) : now(config('shop.timezone'))->next(CarbonInterface::FRIDAY);
                            $set('starts_at', $friday->copy()->startOfDay()->format('Y-m-d H:i'));
                            $set('ends_at', $friday->copy()->endOfDay()->format('Y-m-d H:i'));
                            $set('repeats_weekly', true);
                        })),
                DateTimePicker::make('ends_at')->label('শেষ')->required()->seconds(false)->native(false)->after('starts_at'),
            ]),
            Repeater::make('items')->label('পণ্য')->relationship()->orderColumn('sort_order')
                ->columns(['default' => 1, 'md' => 4])->minItems(1)->addActionLabel('পণ্য যোগ করুন')
                ->schema([
                    Select::make('product_id')->label('পণ্য')->required()->searchable()->columnSpan(['md' => 2])
                        ->options(fn () => Product::orderBy('name')->pluck('name', 'id'))
                        ->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                    Select::make('discount_type')->label('ধরন')->options(DiscountType::class)->required()->native(false)->live()->default(DiscountType::Percent),
                    TextInput::make('discount_value')->label('ছাড়')->numeric()->required()->minValue(1)
                        ->suffix(fn (Get $get) => self::isPercent($get) ? '%' : '৳')
                        ->maxValue(fn (Get $get) => self::isPercent($get) ? 100 : null)
                        // Amounts are typed in taka and stored in poisha.
                        ->formatStateUsing(fn ($state, Get $get) => $state === null ? null : (self::isPercent($get) ? $state : $state / 100))
                        ->dehydrateStateUsing(fn ($state, Get $get) => self::isPercent($get) ? (int) $state : (int) round($state * 100)),
                ]),
            Grid::make(2)->schema([
                Toggle::make('is_active')->label('চালু')->default(true)->helperText('সময় হলে নিজে থেকে শুরু ও শেষ হবে।'),
                Toggle::make('repeats_weekly')->label('প্রতি সপ্তাহে একই দিনে চলবে')
                    ->helperText('যেমন জুম্মার অফার: শুরু-শেষ এই শুক্রবারে দিন, শেষ হলে নিজে থেকে পরের শুক্রবারে চলে যাবে।'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('items'))
            ->columns([
                TextColumn::make('name')->label('নাম')->weight('bold')->searchable(),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->state(fn (FlashSale $record) => match (true) {
                        ! $record->is_active => 'বন্ধ',
                        $record->starts_at->isFuture() => 'আসছে',
                        $record->ends_at->isPast() => 'শেষ',
                        default => 'চলছে',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'চলছে' => 'success',
                        'আসছে' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('starts_at')->label('শুরু')->dateTime('d M, h:i A'),
                TextColumn::make('ends_at')->label('শেষ')->dateTime('d M, h:i A')
                    ->description(fn (FlashSale $record) => $record->repeats_weekly ? 'প্রতি সপ্তাহে' : null),
                TextColumn::make('items_count')->label('পণ্য'),
                ToggleColumn::make('is_active')->label('চালু'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFlashSales::route('/')];
    }

    private static function isPercent(Get $get): bool
    {
        $type = $get('discount_type');

        return ($type instanceof DiscountType ? $type : DiscountType::tryFrom((string) $type)) !== DiscountType::Amount;
    }
}

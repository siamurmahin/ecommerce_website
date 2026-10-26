<?php

namespace App\Filament\Resources\Expenses;

use App\Domain\Finance\Models\Expense;
use App\Domain\Order\Models\Order;
use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Support\Fields;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Business costs (ads, packaging, salary…); the sales report's লাভ-ক্ষতি subtracts them for net profit. */
class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'রিপোর্ট';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'খরচ';

    protected static ?string $pluralModelLabel = 'খরচের তালিকা';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('category')->label('ধরন')->options(Expense::CATEGORIES)->default('ads')->required()->native(false)->live(),
                DatePicker::make('spent_on')->label('তারিখ')->default(now(config('shop.timezone')))->required()->native(false),
                TextInput::make('title')->label('বিবরণ')->placeholder('যেমন: ফেসবুক অ্যাড — ঈদ ক্যাম্পেইন')->required()->maxLength(150),
                Fields::money('amount', 'টাকা')->required()->minValue(0.01),
                TextInput::make('campaign')->label('ক্যাম্পেইন (utm_campaign)')->maxLength(150)
                    ->datalist(fn () => Order::query()->latest('id')->limit(500)->pluck('utm')->pluck('utm_campaign')->filter()->unique()->values()->all())
                    ->helperText('বিজ্ঞাপনের লিংকে যে utm_campaign দিয়েছেন — দিলে রিপোর্টে এই ক্যাম্পেইনের ROAS দেখা যাবে।')
                    ->visible(fn (Get $get) => $get('category') === 'ads')->columnSpanFull(),
                Textarea::make('note')->label('নোট')->rows(2)->maxLength(1000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('spent_on', 'desc')
            ->columns([
                TextColumn::make('spent_on')->label('তারিখ')->date('d M Y')->sortable(),
                TextColumn::make('category')->label('ধরন')->badge()->formatStateUsing(fn (string $state) => Expense::CATEGORIES[$state] ?? $state),
                TextColumn::make('title')->label('বিবরণ')->searchable()->wrap()->description(fn (Expense $record) => $record->note),
                TextColumn::make('campaign')->label('ক্যাম্পেইন')->placeholder('—')->searchable()->toggleable(),
                TextColumn::make('amount')->label('টাকা')->formatStateUsing(fn ($state) => Money::format((int) $state))->alignEnd()->sortable()
                    ->summarize(Sum::make()->label('মোট')->formatStateUsing(fn ($state) => Money::format((int) $state))),
                TextColumn::make('user.name')->label('যোগ করেছেন')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')->label('ধরন')->options(Expense::CATEGORIES)->multiple(),
                Filter::make('date')->schema([
                    DatePicker::make('from')->label('থেকে'),
                    DatePicker::make('until')->label('পর্যন্ত'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('spent_on', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('spent_on', '<=', $d))),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('এখনো কোনো খরচ যোগ করা হয়নি')
            ->emptyStateDescription('বিজ্ঞাপন, প্যাকেজিং, বেতনের মতো খরচ লিখে রাখুন — সেলস রিপোর্টের "লাভ-ক্ষতি"-তে নেট প্রফিট হিসাব হবে।');
    }

    public static function getPages(): array
    {
        return ['index' => ManageExpenses::route('/')];
    }
}

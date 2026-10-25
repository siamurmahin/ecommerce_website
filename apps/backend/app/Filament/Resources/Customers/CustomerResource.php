<?php

namespace App\Filament\Resources\Customers;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Enums\OrderStatus;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PointsRelationManager;
use App\Support\Money;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'কাস্টমার';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'phone';

    protected static ?string $modelLabel = 'কাস্টমার';

    protected static ?string $pluralModelLabel = 'কাস্টমার';

    public static function getGloballySearchableAttributes(): array
    {
        return ['phone', 'name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('phone')->label('মোবাইল')->required()->tel()->maxLength(20)
                    ->dehydrateStateUsing(fn (?string $state) => Phone::normalize($state))
                    ->rule('regex:'.Phone::PATTERN)->unique(ignoreRecord: true)
                    ->disabledOn('edit')->helperText('নম্বরই কাস্টমারের লগইন, পরে বদলানো যায় না'),
                TextInput::make('name')->label('নাম')->maxLength(100),
                TextInput::make('email')->label('ইমেইল')->email()->maxLength(150),
                Toggle::make('is_blocked')->label('ব্লক')->inline(false)->live()
                    ->helperText('ওয়েবসাইট থেকে অর্ডার আর লগইন বন্ধ; ফোনে অর্ডার নেওয়া যাবে'),
                TextInput::make('blocked_reason')->label('ব্লকের কারণ')->maxLength(255)->columnSpanFull()
                    ->visible(fn (Get $get) => (bool) $get('is_blocked')),
                Textarea::make('note')->label('নোট (শুধু স্টাফ দেখবে)')->rows(2)->maxLength(500)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(2)->schema([
                TextEntry::make('name')->label('নাম')->placeholder('—'),
                TextEntry::make('phone')->label('মোবাইল')->copyable()->url(fn (Customer $record) => 'tel:'.$record->phone),
                TextEntry::make('email')->label('ইমেইল')->placeholder('—'),
                TextEntry::make('last_login_at')->label('শেষ লগইন')->since()->placeholder('কখনো লগইন করেননি'),
                TextEntry::make('note')->label('নোট')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make()->columnSpan(1)->schema([
                TextEntry::make('orders_count')->label('অর্ডার (বাতিল বাদে)'),
                TextEntry::make('orders_sum_total')->label('মোট কেনাকাটা')->formatStateUsing(fn ($state) => Money::format((int) $state)),
                TextEntry::make('created_at')->label('প্রথম দেখা')->date('d M Y'),
                IconEntry::make('is_blocked')->label('ব্লক')->boolean()->trueColor('danger')->falseColor('gray'),
                TextEntry::make('blocked_reason')->label('কারণ')->visible(fn (Customer $record) => $record->is_blocked),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('কাস্টমার')->placeholder('নাম নেই')->description(fn (Customer $record) => $record->phone)
                    ->searchable(['name', 'phone']),
                TextColumn::make('orders_count')->label('অর্ডার')->numeric()->sortable(),
                TextColumn::make('orders_sum_total')->label('মোট কেনাকাটা')->formatStateUsing(fn ($state) => Money::format((int) $state))->sortable(),
                TextColumn::make('points_balance')->label('পয়েন্ট')->numeric()->sortable()->toggleable(),
                TextColumn::make('orders_max_created_at')->label('শেষ অর্ডার')->since()->sortable()->placeholder('—'),
                TextColumn::make('last_login_at')->label('শেষ লগইন')->since()->sortable()->placeholder('—')->toggleable(),
                IconColumn::make('is_blocked')->label('ব্লক')->boolean()->trueIcon(Heroicon::OutlinedNoSymbol)->trueColor('danger')
                    ->falseIcon(null),
            ])
            ->filters([
                TernaryFilter::make('is_blocked')->label('ব্লক'),
                TernaryFilter::make('account')->label('অ্যাকাউন্টে লগইন করেছেন')
                    ->queries(true: fn (Builder $query) => $query->whereNotNull('last_login_at'), false: fn (Builder $query) => $query->whereNull('last_login_at')),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    /** Order count, spend and last order date (cancelled orders don't count). */
    public static function getEloquentQuery(): Builder
    {
        $counted = fn (Builder $query) => $query->where('status', '!=', OrderStatus::Cancelled);

        return parent::getEloquentQuery()
            ->withCount(['orders' => $counted])
            ->withSum(['orders' => $counted], 'total')
            ->withMax('orders', 'created_at');
    }

    public static function getRelations(): array
    {
        return [OrdersRelationManager::class, AddressesRelationManager::class, PointsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}

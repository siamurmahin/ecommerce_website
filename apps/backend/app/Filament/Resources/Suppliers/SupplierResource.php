<?php

namespace App\Filament\Resources\Suppliers;

use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\Supplier;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'ক্যাটালগ';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'সাপ্লায়ার';

    protected static ?string $pluralModelLabel = 'সাপ্লায়ার';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')->label('নাম / প্রতিষ্ঠান')->required()->maxLength(150),
                TextInput::make('contact_person')->label('যোগাযোগের ব্যক্তি')->maxLength(100),
                TextInput::make('phone')->label('মোবাইল')->tel()->maxLength(20),
                TextInput::make('email')->label('ইমেইল')->email()->maxLength(150),
                Textarea::make('address')->label('ঠিকানা')->rows(2)->maxLength(500)->columnSpanFull(),
                Textarea::make('note')->label('নোট')->rows(2)->maxLength(1000)->columnSpanFull(),
                Toggle::make('is_active')->label('সক্রিয়')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $open = fn (Builder $q) => $q->where('status', '!=', PurchaseStatus::Cancelled);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['purchaseOrders' => $open])->withSum(['purchaseOrders' => $open], 'total')->withSum(['purchaseOrders' => $open], 'paid_amount'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('সাপ্লায়ার')->searchable(['name', 'phone', 'contact_person'])
                    ->description(fn (Supplier $record) => collect([$record->contact_person, $record->phone])->filter()->implode(' · ') ?: null),
                TextColumn::make('purchase_orders_count')->label('পারচেজ')->numeric()->sortable(),
                TextColumn::make('purchase_orders_sum_total')->label('মোট কেনা')->formatStateUsing(fn ($state) => Money::format((int) $state))->sortable(),
                TextColumn::make('due')->label('বাকি')->color('danger')
                    ->state(fn (Supplier $record) => max(0, (int) $record->purchase_orders_sum_total - (int) $record->purchase_orders_sum_paid_amount))
                    ->formatStateUsing(fn (int $state) => $state > 0 ? Money::format($state) : '—'),
                IconColumn::make('is_active')->label('সক্রিয়')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()->disabled(fn (Supplier $record) => $record->purchase_orders_count > 0)->tooltip('পারচেজ অর্ডার থাকলে মোছা যায় না')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSuppliers::route('/')];
    }
}

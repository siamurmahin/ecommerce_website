<?php

namespace App\Filament\Resources\PurchaseOrders;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Support\Fields;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'ক্যাটালগ';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'পারচেজ অর্ডার';

    protected static ?string $pluralModelLabel = 'পারচেজ অর্ডার';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $waiting = PurchaseOrder::where('status', PurchaseStatus::Ordered)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'মাল আসার অপেক্ষায়';
    }

    public static function form(Schema $schema): Schema
    {
        $locked = fn (?PurchaseOrder $record) => $record?->exists && ! $record->status->editable();

        return $schema->columns(1)->components([
            Section::make()->schema([
                Grid::make(3)->schema([
                    Select::make('supplier_id')->label('সাপ্লায়ার')->relationship('supplier', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()->preload()->required()->disabled($locked)
                        ->createOptionForm([TextInput::make('name')->label('নাম')->required(), TextInput::make('phone')->label('মোবাইল')->tel()]),
                    DatePicker::make('ordered_at')->label('অর্ডারের তারিখ')->default(now())->native(false)->disabled($locked),
                    DatePicker::make('expected_at')->label('মাল আসার সম্ভাব্য তারিখ')->native(false)->disabled($locked),
                ]),
                Textarea::make('note')->label('নোট')->rows(2)->maxLength(1000),
            ]),
            Section::make('পণ্য')
                ->description('"মাল গ্রহণ করুন" চাপলে এই পরিমাণগুলো স্টকে যোগ হবে আর পণ্যের ক্রয় মূল্য আপডেট হবে।')
                ->schema([
                    Repeater::make('items')->relationship()->hiddenLabel()->addActionLabel('পণ্য যোগ করুন')->minItems(1)
                        ->disabled($locked)
                        ->table([
                            Repeater\TableColumn::make('পণ্য'),
                            Repeater\TableColumn::make('ভ্যারিয়েন্ট'),
                            Repeater\TableColumn::make('পরিমাণ')->width('110px'),
                            Repeater\TableColumn::make('প্রতি পিস ক্রয় মূল্য')->width('170px'),
                        ])
                        ->schema([
                            Select::make('product_id')->hiddenLabel()->required()->searchable()->live()
                                ->getSearchResultsUsing(fn (string $search) => Product::where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")->limit(30)->pluck('name', 'id'))
                                ->getOptionLabelUsing(fn ($value) => Product::withTrashed()->find($value)?->name)
                                ->afterStateUpdated(fn ($set, $state) => [$set('product_variant_id', null), $set('unit_cost', Money::toTaka(Product::find($state)?->cost_price))]),
                            Select::make('product_variant_id')->hiddenLabel()->placeholder('—')
                                ->options(fn (Get $get) => ProductVariant::with('attributeValues.attribute')->where('product_id', $get('product_id'))->get()
                                    ->mapWithKeys(fn (ProductVariant $v) => [$v->id => $v->label() ?: $v->sku])->all())
                                ->required(fn (Get $get) => ProductVariant::where('product_id', $get('product_id'))->exists())
                                ->disabled(fn (Get $get) => ! ProductVariant::where('product_id', $get('product_id'))->exists()),
                            TextInput::make('quantity')->hiddenLabel()->numeric()->integer()->minValue(1)->default(1)->required(),
                            Fields::money('unit_cost', '')->hiddenLabel()->required(),
                        ]),
                ]),
            Text::make(fn (?PurchaseOrder $record) => $record ? 'মোট: '.Money::format($record->total).' · পরিশোধ: '.Money::format($record->paid_amount).' · বাকি: '.Money::format($record->due()) : '')
                ->visible(fn (?PurchaseOrder $record) => $record !== null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('supplier')->withCount('items'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('নম্বর')->searchable()->description(fn (PurchaseOrder $record) => $record->supplier?->name),
                TextColumn::make('items_count')->label('পণ্য')->numeric(),
                TextColumn::make('total')->label('মোট')->formatStateUsing(fn ($state) => Money::format((int) $state))->sortable(),
                TextColumn::make('due')->label('বাকি')->state(fn (PurchaseOrder $record) => $record->due())
                    ->formatStateUsing(fn (int $state) => $state > 0 ? Money::format($state) : '—')->color('danger'),
                TextColumn::make('status')->label('স্ট্যাটাস')->badge(),
                TextColumn::make('expected_at')->label('আসার কথা')->date('d M Y')->placeholder('—')->sortable(),
                TextColumn::make('received_at')->label('এসেছে')->date('d M Y')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('স্ট্যাটাস')->options(PurchaseStatus::class),
                SelectFilter::make('supplier_id')->label('সাপ্লায়ার')->relationship('supplier', 'name')->searchable()->preload(),
            ])
            ->recordActions([...self::workflowActions(), EditAction::make()]);
    }

    /** Ordered → received, payments and cancelling; used on the table and the edit page. */
    public static function workflowActions(): array
    {
        return [
            Action::make('markOrdered')->label('সাপ্লায়ারকে অর্ডার দেওয়া হয়েছে')->icon(Heroicon::OutlinedPaperAirplane)->color('warning')
                ->visible(fn (PurchaseOrder $record) => $record->status === PurchaseStatus::Draft)
                ->authorize('update')
                ->action(fn (PurchaseOrder $record) => $record->update(['status' => PurchaseStatus::Ordered, 'ordered_at' => $record->ordered_at ?? now()])),
            Action::make('receive')->label('মাল গ্রহণ করুন')->icon(Heroicon::OutlinedInboxArrowDown)->color('success')
                ->visible(fn (PurchaseOrder $record) => $record->status->editable())
                ->authorize('update')
                ->requiresConfirmation()
                ->modalDescription('সব পণ্যের পরিমাণ স্টকে যোগ হবে। এরপর এই অর্ডার আর বদলানো যাবে না।')
                ->action(function (PurchaseOrder $record) {
                    app(ReceivePurchaseOrder::class)->handle($record);
                    Notification::make()->success()->title('স্টকে যোগ হয়েছে')->send();
                }),
            Action::make('payment')->label('পেমেন্ট যোগ')->icon(Heroicon::OutlinedBanknotes)->color('gray')
                ->visible(fn (PurchaseOrder $record) => $record->status !== PurchaseStatus::Cancelled && $record->due() > 0)
                ->authorize('update')
                ->schema(fn (PurchaseOrder $record) => [
                    Fields::money('amount', 'পরিমাণ')->required()->default(Money::toTaka($record->due()))->formatStateUsing(null)
                        ->helperText('বাকি '.Money::format($record->due())),
                ])
                ->action(fn (PurchaseOrder $record, array $data) => $record->update(['paid_amount' => min($record->total, $record->paid_amount + (int) $data['amount'])])),
            Action::make('cancel')->label('বাতিল')->icon(Heroicon::OutlinedXMark)->color('danger')
                ->visible(fn (PurchaseOrder $record) => $record->status->editable())
                ->authorize('update')
                ->requiresConfirmation()
                ->action(fn (PurchaseOrder $record) => $record->update(['status' => PurchaseStatus::Cancelled])),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}

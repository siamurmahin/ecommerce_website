<?php

namespace App\Filament\Resources\OrderReturns;

use App\Domain\Order\Models\Order;
use App\Domain\Returns\Actions\ReceiveReturn;
use App\Domain\Returns\Enums\ReturnReason;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Enums\ReturnType;
use App\Domain\Returns\Models\OrderReturn;
use App\Filament\Resources\OrderReturns\Pages\ListOrderReturns;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Fields;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Returns and exchanges, with their reasons: approve, receive the parcel (stock back), refund or send the replacement. */
class OrderReturnResource extends Resource
{
    protected static ?string $model = OrderReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'অর্ডার';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'রিটার্ন ও এক্সচেঞ্জ';

    protected static ?string $modelLabel = 'রিটার্ন/এক্সচেঞ্জ';

    protected static ?string $pluralModelLabel = 'রিটার্ন ও এক্সচেঞ্জ';

    public static function canCreate(): bool
    {
        return false; // started from an order (অর্ডার → রিটার্ন / এক্সচেঞ্জ) or by the customer
    }

    public static function getNavigationBadge(): ?string
    {
        $count = OrderReturn::where('status', ReturnStatus::Requested)->count();

        return $count > 0 ? (string) $count : null;
    }

    /** The fields staff fill when starting a return from an order. */
    public static function requestFields(Order $order): array
    {
        $items = $order->items->mapWithKeys(fn ($i) => [$i->id => trim("{$i->name} {$i->variant_label}")." (×{$i->quantity})"])->all();

        return [
            Select::make('type')->label('ধরন')->options(ReturnType::class)->default(ReturnType::Return)->required()->native(false),
            Select::make('reason')->label('কারণ')->options(ReturnReason::class)->required()->native(false),
            Repeater::make('items')->label('যে পণ্য ফেরত আসছে')
                ->schema([
                    Select::make('order_item_id')->hiddenLabel()->options($items)->required()->distinct()->native(false),
                    TextInput::make('quantity')->hiddenLabel()->numeric()->integer()->minValue(1)
                        ->maxValue(fn (Get $get) => $order->items->firstWhere('id', (int) $get('order_item_id'))?->quantity ?? 1)->default(1)->required(),
                ])
                ->columns(2)->minItems(1)->reorderable(false)
                ->default($order->items->map(fn ($i) => ['order_item_id' => $i->id, 'quantity' => $i->quantity])->all())
                ->addActionLabel('পণ্য যোগ করুন'),
            Textarea::make('note')->label('কাস্টমার কী বলেছেন')->rows(2)->maxLength(1000),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order', 'exchangeOrder']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('সময়')->since()->sortable(),
                TextColumn::make('order.number')->label('অর্ডার')->searchable()
                    ->url(fn (OrderReturn $record) => $record->order ? OrderResource::getUrl('view', ['record' => $record->order]) : null)
                    ->description(fn (OrderReturn $record) => $record->order?->customer_name),
                TextColumn::make('type')->label('ধরন')->badge(),
                TextColumn::make('reason')->label('কারণ')->description(fn (OrderReturn $record) => $record->note)->wrap(),
                TextColumn::make('items')->label('পণ্য')->state(fn (OrderReturn $record) => $record->summary())->wrap(),
                TextColumn::make('status')->label('অবস্থা')->badge(),
                TextColumn::make('refund_amount')->label('ফেরত')->formatStateUsing(fn ($state) => $state ? Money::format((int) $state) : '—')->alignEnd()
                    ->description(fn (OrderReturn $record) => OrderReturn::REFUND_METHODS[$record->refund_method] ?? $record->exchangeOrder?->number),
                TextColumn::make('source')->label('কে চেয়েছে')->formatStateUsing(fn (string $state) => $state === 'customer' ? 'কাস্টমার (ওয়েবসাইট)' : 'স্টাফ')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(ReturnStatus::class)->multiple()
                    ->default([ReturnStatus::Requested->value, ReturnStatus::Approved->value, ReturnStatus::Received->value]),
                SelectFilter::make('type')->label('ধরন')->options(ReturnType::class),
                SelectFilter::make('reason')->label('কারণ')->options(ReturnReason::class)->multiple(),
            ])
            ->recordActions([
                Action::make('approve')->label('অনুমোদন')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (OrderReturn $record) => $record->status === ReturnStatus::Requested)->authorize('update')
                    ->schema([Textarea::make('staff_note')->label('নোট (কুরিয়ার, কবে আসবে…)')->rows(2)->maxLength(1000)])
                    ->action(fn (OrderReturn $record, array $data) => $record->update(['status' => ReturnStatus::Approved, 'staff_note' => $data['staff_note'] ?: $record->staff_note, 'user_id' => auth()->id()])),
                Action::make('receive')->label('পণ্য ফেরত এসেছে')->icon(Heroicon::OutlinedInboxArrowDown)->color('info')
                    ->visible(fn (OrderReturn $record) => in_array($record->status, [ReturnStatus::Requested, ReturnStatus::Approved], true))->authorize('update')
                    ->modalDescription('পুরো অর্ডার ফেরত এলে অর্ডারটা "রিটার্নড" হবে; কিছু পণ্য হলে শুধু সেগুলো স্টকে ফিরবে।')
                    ->schema([Toggle::make('restock')->label('ফেরত পণ্য আবার স্টকে যোগ করুন')->helperText('নষ্ট পণ্য হলে বন্ধ রাখুন।')->default(true)])
                    ->action(function (OrderReturn $record, array $data) {
                        app(ReceiveReturn::class)->handle($record, (bool) $data['restock'], auth()->id());
                        Notification::make()->success()->title('ফেরত গ্রহণ করা হয়েছে')->send();
                    }),
                Action::make('complete')->label(fn (OrderReturn $record) => $record->type === ReturnType::Return ? 'টাকা ফেরত দেওয়া হয়েছে' : 'বদলি পণ্য পাঠানো হয়েছে')
                    ->icon(Heroicon::OutlinedBanknotes)->color('success')
                    ->visible(fn (OrderReturn $record) => $record->status === ReturnStatus::Received)->authorize('update')
                    ->fillForm(fn (OrderReturn $record) => ['refund_amount' => Money::toTaka($record->type === ReturnType::Return ? $record->itemsValue() : 0)])
                    ->schema(fn (OrderReturn $record) => [
                        Fields::money('refund_amount', 'ফেরত টাকা')->helperText('এক্সচেঞ্জে দামের পার্থক্য ফেরত দিলে লিখুন, নইলে ০।')->required()->minValue(0),
                        Select::make('refund_method')->label('কীভাবে')->options(OrderReturn::REFUND_METHODS)->native(false)
                            ->required(fn (Get $get) => (float) $get('refund_amount') > 0),
                        Select::make('exchange_order_id')->label('বদলি পণ্যের অর্ডার')->visible($record->type === ReturnType::Exchange)
                            ->searchable()->getSearchResultsUsing(fn (string $search) => Order::where('number', 'like', "%{$search}%")->latest('id')->limit(20)->pluck('number', 'id')->all())
                            ->helperText('অর্ডার → নতুন অর্ডার দিয়ে বদলি পণ্যের অর্ডার বানিয়ে এখানে বেছে নিন।'),
                    ])
                    ->action(fn (OrderReturn $record, array $data) => $record->update([
                        'status' => ReturnStatus::Completed, 'refund_amount' => (int) ($data['refund_amount'] ?? 0), 'refund_method' => $data['refund_method'] ?? null,
                        'exchange_order_id' => $data['exchange_order_id'] ?? null, 'resolved_at' => now(), 'user_id' => auth()->id(),
                    ])),
                ActionGroup::make([
                    Action::make('reject')->label('বাতিল করুন')->icon(Heroicon::OutlinedXMark)->color('danger')
                        ->visible(fn (OrderReturn $record) => in_array($record->status, [ReturnStatus::Requested, ReturnStatus::Approved], true))->authorize('update')
                        ->schema([Textarea::make('staff_note')->label('কারণ')->rows(2)->required()->maxLength(1000)])
                        ->action(fn (OrderReturn $record, array $data) => $record->update(['status' => ReturnStatus::Rejected, 'staff_note' => $data['staff_note'], 'resolved_at' => now(), 'user_id' => auth()->id()])),
                    Action::make('note')->label('নোট')->icon(Heroicon::OutlinedPencilSquare)->authorize('update')
                        ->fillForm(fn (OrderReturn $record) => ['staff_note' => $record->staff_note])
                        ->schema([Textarea::make('staff_note')->label('স্টাফ নোট')->rows(3)->maxLength(1000)])
                        ->action(fn (OrderReturn $record, array $data) => $record->update(['staff_note' => $data['staff_note']])),
                ]),
            ])
            ->emptyStateHeading('কোনো রিটার্ন বা এক্সচেঞ্জ নেই')
            ->emptyStateDescription('কাস্টমার অর্ডার পেজ থেকে অনুরোধ করলে বা অর্ডারের পাতায় "রিটার্ন / এক্সচেঞ্জ" চাপলে এখানে আসবে।');
    }

    public static function getPages(): array
    {
        return ['index' => ListOrderReturns::route('/')];
    }
}

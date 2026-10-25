<?php

namespace App\Filament\Resources\IncompleteOrders;

use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Enums\IncompleteStatus;
use App\Domain\Order\Models\IncompleteOrder;
use App\Filament\Actions\FraudCheckAction;
use App\Filament\Resources\IncompleteOrders\Pages\ManageIncompleteOrders;
use App\Filament\Resources\Orders\OrderResource;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/** Checkouts that stopped after the phone number: call, convert to an order, or discard. */
class IncompleteOrderResource extends Resource
{
    protected static ?string $model = IncompleteOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'অর্ডার';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'ইনকমপ্লিট অর্ডার';

    protected static ?string $pluralModelLabel = 'ইনকমপ্লিট অর্ডার';

    protected static ?string $slug = 'incomplete-orders';

    public static function getNavigationBadge(): ?string
    {
        $count = IncompleteOrder::where('status', IncompleteStatus::Open)->where('last_activity_at', '>=', now()->subDays(7))->count();

        return $count ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['district', 'thana', 'order', 'fraudCheck']))
            ->defaultSort('last_activity_at', 'desc')
            ->poll('60s')
            ->columns([
                TextColumn::make('customer_name')->label('কাস্টমার')->placeholder('নাম দেয়নি')
                    ->searchable(['customer_name', 'customer_phone'])
                    ->description(fn (IncompleteOrder $record) => $record->customer_phone),
                TextColumn::make('items')->label('কার্ট')->wrap()
                    ->formatStateUsing(fn (IncompleteOrder $record) => self::itemsSummary($record))
                    ->description(fn (IncompleteOrder $record) => Money::format($record->subtotal)),
                TextColumn::make('district.name')->label('ঠিকানা')->placeholder('—')->wrap()->toggleable()
                    ->description(fn (IncompleteOrder $record) => collect([$record->thana?->name, $record->address])->filter()->implode(', ') ?: null),
                TextColumn::make('fraudCheck.success_ratio')->label('ডেলিভারি রেশিও')->badge()->toggleable()
                    ->formatStateUsing(fn ($state) => $state === null ? 'নতুন' : "{$state}%")
                    ->color(fn (IncompleteOrder $record) => $record->fraudCheck?->riskColor() ?? 'gray')
                    ->placeholder('—'),
                TextColumn::make('status')->label('স্ট্যাটাস')->badge()
                    ->description(fn (IncompleteOrder $record) => $record->order?->number ?? $record->staff_note),
                TextColumn::make('recovery_sms_at')->label('রিকভারি SMS')->since()->placeholder('—')->toggleable()
                    ->description(fn (IncompleteOrder $record) => $record->recovered_at ? 'লিংকে ঢুকেছেন' : null),
                TextColumn::make('last_activity_at')->label('সময়')->since()->sortable()
                    ->tooltip(fn (IncompleteOrder $record) => $record->last_activity_at->format('d M Y, h:i A')),
            ])
            ->filters([
                SelectFilter::make('status')->label('স্ট্যাটাস')->options(IncompleteStatus::class)->multiple()
                    ->default([IncompleteStatus::Open->value, IncompleteStatus::Contacted->value]),
            ])
            ->recordActions([
                Action::make('call')->label('কল')->icon(Heroicon::OutlinedPhone)->button()->color('gray')
                    ->url(fn (IncompleteOrder $record) => 'tel:'.$record->customer_phone),
                Action::make('convert')->label('অর্ডার তৈরি')->icon(Heroicon::OutlinedPlusCircle)->button()
                    ->visible(fn (IncompleteOrder $record) => $record->status !== IncompleteStatus::Converted && auth()->user()->can('Create:Order'))
                    ->url(fn (IncompleteOrder $record) => OrderResource::getUrl('create', ['draft' => $record->id])),
                ActionGroup::make([
                    Action::make('contacted')->label('যোগাযোগ করেছি')->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                        ->authorize(fn (IncompleteOrder $record) => auth()->user()->can('update', $record))
                        ->fillForm(fn (IncompleteOrder $record) => ['staff_note' => $record->staff_note])
                        ->schema([Textarea::make('staff_note')->label('কী কথা হলো')->rows(2)->maxLength(500)])
                        ->action(fn (IncompleteOrder $record, array $data) => $record->update([
                            'status' => IncompleteStatus::Contacted,
                            'staff_note' => $data['staff_note'] ?? null,
                            'contacted_at' => now(),
                            'user_id' => auth()->id(),
                        ])),
                    Action::make('discard')->label('বাদ দিন')->icon(Heroicon::OutlinedXMark)->color('danger')
                        ->authorize(fn (IncompleteOrder $record) => auth()->user()->can('update', $record))
                        ->visible(fn (IncompleteOrder $record) => $record->status !== IncompleteStatus::Discarded)
                        ->action(fn (IncompleteOrder $record) => $record->update(['status' => IncompleteStatus::Discarded, 'user_id' => auth()->id()])),
                    FraudCheckAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('discard')->label('বাদ দিন')->icon(Heroicon::OutlinedXMark)
                        ->authorize(fn () => auth()->user()->can('Update:IncompleteOrder'))
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => IncompleteOrder::whereKey($records->modelKeys())
                            ->where('status', '!=', IncompleteStatus::Converted)
                            ->update(['status' => IncompleteStatus::Discarded, 'user_id' => auth()->id()])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageIncompleteOrders::route('/')];
    }

    private static function itemsSummary(IncompleteOrder $record): string
    {
        $items = collect($record->items);
        $names = Product::whereIn('id', $items->pluck('product_id'))->pluck('name', 'id');

        return $items->map(fn ($item) => ($names[$item['product_id']] ?? '#'.$item['product_id']).' ×'.($item['quantity'] ?? 1))->implode(', ');
    }
}

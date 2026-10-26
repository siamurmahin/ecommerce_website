<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentMethod;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\Order;
use App\Filament\Actions\BookCourierAction;
use App\Filament\Actions\ChangeStatusAction;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('items')->with(['latestShipment', 'fraudCheck']))
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('number')->label('অর্ডার')->weight('bold')->searchable()->copyable()
                    ->description(fn (Order $record) => $record->created_at->format('d M, h:i A')),
                TextColumn::make('customer_name')->label('কাস্টমার')->searchable(['customer_name', 'customer_phone'])
                    ->description(fn (Order $record) => $record->customer_phone),
                TextColumn::make('district_name')->label('জেলা')->toggleable(),
                TextColumn::make('items_count')->label('পণ্য')->alignCenter(),
                TextColumn::make('total')->label('মোট')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
                TextColumn::make('status')->label('স্ট্যাটাস')->badge()
                    ->description(fn (Order $record) => $record->latestShipment ? $record->latestShipment->courierLabel().' · '.$record->latestShipment->status->getLabel() : null),
                TextColumn::make('fraudCheck.success_ratio')->label('ডেলিভারি রেশিও')->badge()->toggleable()
                    ->formatStateUsing(fn ($state) => $state === null ? 'নতুন' : "{$state}%")
                    ->color(fn (Order $record) => $record->fraudCheck?->riskColor() ?? 'gray')
                    ->tooltip(fn (Order $record) => $record->fraudCheck?->summary())
                    ->placeholder('—'),
                TextColumn::make('payment_status')->label('পেমেন্ট')->badge()->toggleable()
                    ->description(fn (Order $record) => $record->payment_method->isWallet() ? $record->payment_method->getLabel() : null),
                TextColumn::make('source')->label('সোর্স')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('courier')->label('কুরিয়ার')
                    ->options(fn () => collect(config('courier.couriers'))->map(fn ($class) => $class::label())->all() + ['none' => 'বুক করা হয়নি'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'none' => $query->whereDoesntHave('shipments'),
                        default => $query->whereHas('latestShipment', fn ($q) => $q->where('courier', $data['value'])),
                    }),
                SelectFilter::make('payment_status')->label('পেমেন্ট')->options(PaymentStatus::class),
                SelectFilter::make('payment_method')->label('পেমেন্ট মাধ্যম')->options(PaymentMethod::class),
                SelectFilter::make('district_id')->label('জেলা')->relationship('district', 'name')->searchable()->preload(),
                SelectFilter::make('landing_page_id')->label('ল্যান্ডিং পেজ')->relationship('landingPage', 'title')->searchable()->preload(),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('তারিখ থেকে'),
                        DatePicker::make('until')->label('তারিখ পর্যন্ত'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (Order $record) => route('filament.admin.resources.orders.view', $record))
            ->recordActions([
                ChangeStatusAction::make()->iconButton(),
                Action::make('invoice')->label('ইনভয়েস')->icon(Heroicon::OutlinedPrinter)->iconButton()
                    ->url(fn (Order $record) => route('admin.invoices', ['orders' => $record->number]))
                    ->openUrlInNewTab(),
                ViewAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('printInvoices')->label('ইনভয়েস প্রিন্ট')->icon(Heroicon::OutlinedPrinter)
                        ->action(fn (Collection $records) => redirect()->route('admin.invoices', ['orders' => $records->pluck('number')->implode(',')])),
                    BulkAction::make('printSlips')->label('প্যাকিং স্লিপ প্রিন্ট')->icon(Heroicon::OutlinedClipboardDocumentList)
                        ->action(fn (Collection $records) => redirect()->route('admin.invoices', ['orders' => $records->pluck('number')->implode(','), 'type' => 'slip'])),
                    BookCourierAction::bulk(),
                    BulkAction::make('confirm')->label('কনফার্ম করুন')->icon(Heroicon::OutlinedCheck)
                        ->requiresConfirmation()
                        ->authorize(fn () => auth()->user()->can('Update:Order'))
                        ->action(fn (Collection $records, ChangeOrderStatus $change) => $records
                            ->where('status', OrderStatus::Pending)
                            ->each(fn (Order $order) => $change->handle($order, OrderStatus::Confirmed, null, auth()->id()))),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

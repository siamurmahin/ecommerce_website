<?php

namespace App\Filament\Pages;

use App\Domain\Courier\Actions\ImportPayout;
use App\Domain\Courier\Actions\SettleCod;
use App\Domain\Courier\Contracts\PaysOut;
use App\Domain\Courier\CourierException;
use App\Domain\Courier\CourierManager;
use App\Domain\Courier\Data\Payout;
use App\Domain\Courier\Models\Shipment;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Fields;
use App\Settings\CourierSettings;
use App\Support\Money;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Courier COD reconciliation: delivered orders whose cash the courier still owes, marking payouts as
 * received (one by one, in bulk, or from the courier's payout CSV) and showing any short payment.
 */
class CodReconciliation extends Page implements HasTable
{
    use HasPageShield, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'রিপোর্ট';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'কুরিয়ার COD মেলানো';

    protected static ?string $title = 'কুরিয়ার COD মেলানো';

    protected static ?string $slug = 'cod-reconciliation';

    public function getSubheading(): ?string
    {
        $open = $this->outstanding()->with('latestShipment')->get();

        return $open->isEmpty() ? 'কুরিয়ারের কাছে কোনো টাকা বাকি নেই।'
            : sprintf('%d টি ডেলিভারড অর্ডারের %s কুরিয়ার থেকে পাওয়া বাকি।', $open->count(), Money::format($open->sum(fn (Order $o) => $o->codExpected())));
    }

    /** Delivered, cash still with the courier. */
    private function outstanding(): Builder
    {
        return Order::query()->where('status', OrderStatus::Delivered->value)->whereNull('cod_settled_at')
            ->whereColumn('paid_amount', '<', 'total');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $money = fn ($state) => $state === null ? '—' : Money::format((int) $state);

        return $table
            ->query(Order::query()->with('latestShipment')
                ->where(fn (Builder $q) => $q->whereNotNull('cod_settled_at')->orWhere(fn (Builder $q) => $q->where('status', OrderStatus::Delivered->value)->whereColumn('paid_amount', '<', 'total'))))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('অর্ডার')->searchable()
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
                    ->description(fn (Order $record) => $record->customer_name),
                TextColumn::make('latestShipment.consignment_id')->label('কুরিয়ার')->searchable()->placeholder('—')
                    ->description(fn (Order $record) => $record->latestShipment?->courierLabel()),
                TextColumn::make('expected')->label('পাওয়ার কথা')->state(fn (Order $record) => $record->codExpected())->formatStateUsing($money)->alignEnd(),
                TextColumn::make('cod_collected')->label('পেয়েছি')->formatStateUsing($money)->alignEnd()->placeholder('—'),
                TextColumn::make('courier_fee')->label('কুরিয়ার চার্জ')->state(fn (Order $record) => $record->courier_fee ?? $record->latestShipment?->delivery_fee)
                    ->formatStateUsing($money)->alignEnd()->placeholder('—'),
                TextColumn::make('difference')->label('পার্থক্য')->state(fn (Order $record) => SettleCod::difference($record))->formatStateUsing($money)->alignEnd()->placeholder('—')
                    ->color(fn ($state) => $state < 0 ? 'danger' : null),
                TextColumn::make('cod_settled_at')->label('অবস্থা')->badge()
                    ->state(fn (Order $record) => $record->cod_settled_at ? 'মিলেছে' : 'বাকি')
                    ->color(fn (Order $record) => $record->cod_settled_at ? 'success' : 'warning')
                    ->description(fn (Order $record) => $record->cod_settlement_ref),
            ])
            ->filters([
                TernaryFilter::make('settled')->label('অবস্থা')->trueLabel('মিলেছে')->falseLabel('বাকি')->default(false)
                    ->queries(true: fn (Builder $q) => $q->whereNotNull('cod_settled_at'), false: fn (Builder $q) => $q->whereNull('cod_settled_at')),
                SelectFilter::make('courier')->label('কুরিয়ার')->options(fn () => Shipment::query()->distinct()->pluck('courier', 'courier')->all())
                    ->query(fn (Builder $q, array $data) => $q->when($data['value'] ?? null, fn ($q, $c) => $q->whereHas('latestShipment', fn ($s) => $s->where('courier', $c)))),
            ])
            ->recordActions([
                Action::make('settle')->label('মেলান')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->authorize(fn () => auth()->user()?->can('update', Order::class))
                    ->fillForm(fn (Order $record) => [
                        'collected' => Money::toTaka($record->cod_collected ?? $record->codExpected()),
                        'fee' => Money::toTaka($record->courier_fee ?? $record->latestShipment?->delivery_fee),
                        'reference' => $record->cod_settlement_ref,
                    ])
                    ->schema([
                        Fields::money('collected', 'কুরিয়ার থেকে পেয়েছি')->required()->minValue(0),
                        Fields::money('fee', 'কুরিয়ার চার্জ কেটেছে'),
                        TextInput::make('reference')->label('পেমেন্ট রেফারেন্স / ইনভয়েস')->maxLength(100),
                    ])
                    ->action(function (Order $record, array $data) {
                        app(SettleCod::class)->handle($record, (int) $data['collected'], isset($data['fee']) ? (int) $data['fee'] : null, $data['reference'] ?? null, auth()->id());
                        Notification::make()->success()->title('মেলানো হয়েছে')->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('settleAll')->label('পুরো টাকা পেয়েছি')->icon(Heroicon::OutlinedCheck)
                    ->authorize(fn () => auth()->user()?->can('update', Order::class))
                    ->schema([TextInput::make('reference')->label('পেমেন্ট রেফারেন্স / ইনভয়েস')->maxLength(100)])
                    ->action(function (Collection $records, array $data) {
                        $records->reject->cod_settled_at->each(fn (Order $o) => app(SettleCod::class)->handle($o, null, null, $data['reference'] ?? null, auth()->id()));
                        Notification::make()->success()->title('মেলানো হয়েছে')->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('কিছু মেলানোর নেই')
            ->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importPayout')->label('কুরিয়ারের পেমেন্ট থেকে মেলান')->icon(Heroicon::OutlinedArrowPath)->color('success')
                ->authorize(fn () => auth()->user()?->can('update', Order::class))
                ->visible(fn () => self::payingCouriers() !== [])
                ->modalDescription('কুরিয়ার যে পেমেন্ট পাঠিয়েছে সেটা বেছে নিন — তার ভেতরের ডেলিভারড পার্সেলগুলোর অর্ডার নিজে থেকে মিলে যাবে। বাতিল/রিটার্ন পার্সেল বাদ যাবে; একই পেমেন্ট দুবার মেলালে কিছু বদলাবে না।')
                ->schema([
                    Select::make('payout')->label('পেমেন্ট')->options(fn () => self::payoutOptions())->required()->native(false)->searchable(),
                ])
                ->action(function (array $data, Action $action) {
                    [$courier, $id] = explode('|', $data['payout'], 2);
                    try {
                        $result = app(ImportPayout::class)->handle($courier, $id, auth()->id());
                    } catch (CourierException $e) {
                        Notification::make()->danger()->title('পেমেন্ট আনা যায়নি')->body($e->getMessage())->persistent()->send();
                        $action->halt();

                        return;
                    }
                    Notification::make()->title("{$result['settled']} টি অর্ডার মেলানো হয়েছে")
                        ->body(collect([
                            $result['already'] ? "{$result['already']} টি আগেই মেলানো ছিল।" : null,
                            $result['skipped'] ? "{$result['skipped']} টি পার্সেলে টাকা আসেনি (বাতিল/রিটার্ন)।" : null,
                            $result['short'] ? "{$result['short']} টিতে টাকা কম এসেছে (পার্থক্য কলাম দেখুন)।" : null,
                            $result['unmatched'] ? 'আমাদের অর্ডারে মেলেনি: '.implode(', ', array_slice($result['unmatched'], 0, 10)) : null,
                        ])->filter()->implode(' '))
                        ->{$result['unmatched'] || $result['short'] ? 'warning' : 'success'}()->persistent()->send();
                }),
            Action::make('import')->label('কুরিয়ারের CSV থেকে মেলান')->icon(Heroicon::OutlinedArrowUpTray)
                ->authorize(fn () => auth()->user()?->can('update', Order::class))
                ->modalDescription('কুরিয়ারের পেমেন্ট রিপোর্ট CSV দিন। প্রথম সারিতে কলামের নাম থাকতে হবে: অর্ডার নম্বর/invoice বা consignment_id, আর টাকা (collected/cod/amount); কুরিয়ার চার্জ (fee/charge) থাকলে সেটাও নেওয়া হবে।')
                ->schema([
                    FileUpload::make('file')->label('CSV ফাইল')->disk('local')->directory('cod-imports')->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])->required(),
                    TextInput::make('reference')->label('পেমেন্ট রেফারেন্স')->maxLength(100),
                ])
                ->action(function (array $data) {
                    $result = self::import(Storage::disk('local')->path($data['file']), $data['reference'] ?? null);
                    Storage::disk('local')->delete($data['file']);
                    Notification::make()->title("{$result['settled']} টি অর্ডার মেলানো হয়েছে")
                        ->body(collect([
                            $result['short'] ? "{$result['short']} টিতে টাকা কম এসেছে (পার্থক্য কলাম দেখুন)।" : null,
                            $result['unmatched'] ? 'মেলেনি: '.implode(', ', array_slice($result['unmatched'], 0, 10)) : null,
                        ])->filter()->implode(' '))
                        ->{$result['unmatched'] || $result['short'] ? 'warning' : 'success'}()->persistent()->send();
                }),
        ];
    }

    /** @return array<string, PaysOut> courier key => driver, for configured couriers whose payouts the API lists */
    private static function payingCouriers(): array
    {
        if (! app(CourierSettings::class)->enabled) {
            return [];
        }
        $couriers = app(CourierManager::class);

        return collect($couriers->configured())->keys()
            ->mapWithKeys(fn (string $key) => [$key => $couriers->driver($key)])
            ->filter(fn ($driver) => $driver instanceof PaysOut)->all();
    }

    /** @return array<string, string> "courier|payout id" => label */
    private static function payoutOptions(): array
    {
        $options = [];
        foreach (self::payingCouriers() as $key => $driver) {
            try {
                $payouts = collect($driver->payouts())->take(50);
            } catch (CourierException $e) {
                Notification::make()->danger()->title('পেমেন্টের তালিকা আনা যায়নি')->body($e->getMessage())->send();

                continue;
            }
            $done = Order::whereIn('cod_settlement_ref', $payouts->pluck('id'))->distinct()->pluck('cod_settlement_ref')->flip();
            foreach ($payouts as $payout) {
                /** @var Payout $payout */
                $options["{$key}|{$payout->id}"] = implode(' · ', array_filter([
                    $driver::label(), $payout->id, Money::format($payout->total), $payout->status,
                    $payout->createdAt?->setTimezone(config('shop.timezone'))->format('d M Y'),
                    isset($done[$payout->id]) ? 'আগে মেলানো' : null,
                ]));
            }
        }

        return $options;
    }

    /**
     * Settle orders from a courier payout CSV.
     *
     * @return array{settled: int, short: int, unmatched: list<string>}
     */
    public static function import(string $path, ?string $reference = null): array
    {
        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle) ?: []);
        $find = fn (array $names) => collect($header)->search(fn ($h) => collect($names)->contains(fn ($n) => str_contains($h, $n)));
        $idColumn = $find(['invoice', 'order', 'merchant', 'consignment', 'tracking']);
        $amountColumn = $find(['collected', 'cod', 'amount', 'cash']);
        $feeColumn = $find(['fee', 'charge']);

        $result = ['settled' => 0, 'short' => 0, 'unmatched' => []];
        if ($idColumn === false || $amountColumn === false) {
            fclose($handle);
            $result['unmatched'][] = 'কলাম চেনা যায়নি';

            return $result;
        }

        while (($row = fgetcsv($handle)) !== false) {
            $id = trim((string) ($row[$idColumn] ?? ''));
            if ($id === '') {
                continue;
            }
            $order = Order::where('number', strtoupper($id))->first()
                ?? Shipment::where('consignment_id', $id)->orWhere('tracking_code', $id)->latest('id')->first()?->order;
            if (! $order) {
                $result['unmatched'][] = $id;

                continue;
            }
            $toPoisha = fn ($v) => (int) round(((float) preg_replace('/[^0-9.]/', '', (string) $v)) * 100);
            $fee = $feeColumn !== false && filled($row[$feeColumn] ?? null) ? $toPoisha($row[$feeColumn]) : null;
            app(SettleCod::class)->handle($order, $toPoisha($row[$amountColumn] ?? 0), $fee, $reference, auth()->id());
            $result['settled']++;
            if ((SettleCod::difference($order->refresh()) ?? 0) < 0) {
                $result['short']++;
            }
        }
        fclose($handle);

        return $result;
    }
}

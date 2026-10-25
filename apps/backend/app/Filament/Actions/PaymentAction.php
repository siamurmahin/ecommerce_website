<?php

namespace App\Filament\Actions;

use App\Domain\Order\Actions\RecordPayment;
use App\Domain\Order\Enums\PaymentMethod;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\Order;
use App\Support\Money;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Pending wallet payment → check the TrxID in the bKash/Nagad app and approve or reject.
 * Otherwise (unpaid / advance only) → record money received later by hand.
 */
final class PaymentAction
{
    public static function make(): Action
    {
        return Action::make('payment')
            ->label(fn (Order $record) => $record->payment_status === PaymentStatus::Pending ? 'পেমেন্ট যাচাই' : 'পেমেন্ট যোগ করুন')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color(fn (Order $record) => $record->payment_status === PaymentStatus::Pending ? 'warning' : 'gray')
            ->visible(fn (Order $record) => in_array($record->payment_status, [PaymentStatus::Pending, PaymentStatus::Unpaid, PaymentStatus::Partial], true))
            ->authorize(fn (Order $record) => auth()->user()->can('update', $record))
            ->modalDescription(fn (Order $record) => $record->payment_status === PaymentStatus::Pending
                ? sprintf('%s অ্যাপে মিলিয়ে দেখুন: %s থেকে %s, TrxID %s।', $record->payment_wallet?->getLabel(), $record->payment_sender, Money::format($record->advance_amount), $record->payment_trx_id)
                : 'কাস্টমার পরে টাকা পাঠালে এখানে লিখে রাখুন। COD-র টাকা কুরিয়ার থেকে এলে এটা লাগবে না।')
            ->fillForm(fn (Order $record) => [
                'decision' => 'approve',
                'amount' => Money::toTaka($record->payment_status === PaymentStatus::Pending ? $record->advance_amount : $record->total - $record->paid_amount),
                'wallet' => $record->payment_wallet?->value ?? PaymentMethod::Bkash->value,
            ])
            ->schema(fn (Order $record) => $record->payment_status === PaymentStatus::Pending
                ? [
                    Radio::make('decision')->hiddenLabel()->live()->required()
                        ->options(['approve' => 'টাকা পেয়েছি', 'reject' => 'মেলেনি — বাতিল করুন']),
                    TextInput::make('amount')->label('কত টাকা পেয়েছেন')->numeric()->prefix('৳')->minValue(1)->required()
                        ->visible(fn (Get $get) => $get('decision') === 'approve'),
                    TextInput::make('reason')->label('কারণ')->placeholder('এই TrxID পাওয়া যায়নি')->maxLength(250)->required()
                        ->visible(fn (Get $get) => $get('decision') === 'reject'),
                ]
                : [
                    Grid::make(2)->schema([
                        Select::make('wallet')->label('মাধ্যম')->options(collect(PaymentMethod::wallets())->mapWithKeys(fn ($m) => [$m->value => $m->getLabel()]))->required()->native(false),
                        TextInput::make('amount')->label('টাকা')->numeric()->prefix('৳')->minValue(1)->required(),
                        TextInput::make('sender')->label('যে নম্বর থেকে')->tel()->regex(Phone::PATTERN)->dehydrateStateUsing(fn ($state) => filled($state) ? Phone::normalize($state) : null),
                        TextInput::make('trx_id')->label('TrxID')->maxLength(30)->regex('/^[A-Za-z0-9]{6,30}$/'),
                    ]),
                    TextInput::make('note')->label('নোট')->maxLength(250),
                ])
            ->action(function (Order $record, array $data, RecordPayment $payments) {
                if (($data['decision'] ?? 'approve') === 'reject') {
                    $payments->reject($record, $data['reason'], auth()->id());
                    Notification::make()->title('পেমেন্ট বাতিল করা হয়েছে')->warning()->send();

                    return;
                }
                $payments->approve($record, (int) Money::toPoisha($data['amount']), auth()->id(), array_intersect_key($data, array_flip(['wallet', 'sender', 'trx_id', 'note'])));
                Notification::make()->title('পেমেন্ট সেভ হয়েছে')->body($record->payment_status->getLabel())->success()->send();
            });
    }
}

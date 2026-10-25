<?php

namespace App\Filament\Actions;

use App\Domain\Fraud\FraudService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** Fresh delivery-history lookup for the record's customer_phone (orders and incomplete orders). */
final class FraudCheckAction
{
    public static function make(): Action
    {
        return Action::make('fraudCheck')
            ->label('ফ্রড চেক')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('gray')
            ->action(function (Model $record, FraudService $fraud) {
                try {
                    $check = $fraud->check($record->customer_phone, fresh: true);
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title('চেক করা যায়নি')->body($e->getMessage())->send();

                    return;
                }

                $record->unsetRelation('fraudCheck');
                Notification::make()
                    ->{$check->risk() === 'risky' ? 'danger' : ($check->risk() === 'good' ? 'success' : 'warning')}()
                    ->title($record->customer_phone)
                    ->body($check->summary())
                    ->send();
            });
    }
}

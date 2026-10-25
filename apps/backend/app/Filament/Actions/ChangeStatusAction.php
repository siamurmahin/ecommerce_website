<?php

namespace App\Filament\Actions;

use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Status change with an optional note; stock moves and history are handled by ChangeOrderStatus. */
final class ChangeStatusAction
{
    public static function make(): Action
    {
        return Action::make('changeStatus')
            ->label('স্ট্যাটাস বদলান')
            ->icon(Heroicon::OutlinedArrowPath)
            ->authorize(fn (Order $record) => auth()->user()->can('update', $record))
            ->fillForm(fn (Order $record) => ['status' => $record->status])
            ->schema([
                Select::make('status')->label('নতুন স্ট্যাটাস')->options(OrderStatus::class)->required()->native(false),
                Textarea::make('note')->label('নোট (ঐচ্ছিক)')->rows(2),
            ])
            ->action(function (Order $record, array $data, ChangeOrderStatus $change) {
                $status = $data['status'] instanceof OrderStatus ? $data['status'] : OrderStatus::from($data['status']);
                $change->handle($record, $status, $data['note'] ?? null, auth()->id());
                Notification::make()->title('স্ট্যাটাস আপডেট হয়েছে')->success()->send();
            });
    }
}

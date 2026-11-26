<?php

namespace App\Domain\Order\Listeners;

use App\Domain\Order\Enums\OrderSource;
use App\Domain\Order\Events\OrderPlaced;
use App\Domain\Order\Models\Order;
use App\Filament\Resources\Orders\OrderResource;
use App\Mail\NewOrderMail;
use App\Models\User;
use App\Settings\NotificationSettings;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

/** A website order came in: email the shop and ring the admin bell for staff who can see orders. */
class NotifyStaffOfNewOrder
{
    public function __construct(private NotificationSettings $settings) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;
        if ($order->source !== OrderSource::Web) {
            return; // staff entered it themselves
        }

        if ($this->settings->new_order_email && ($emails = $this->settings->emailList())) {
            Mail::to($emails)->queue(new NewOrderMail($order));
        }

        if ($this->settings->admin_bell) {
            $staff = User::query()->get()->filter(fn (User $user) => $user->can('viewAny', Order::class));
            if ($staff->isNotEmpty()) {
                Notification::make()
                    ->title("নতুন অর্ডার {$order->number}")
                    ->body("{$order->customer_name} · ".Money::format($order->total))
                    ->icon('heroicon-o-shopping-bag')->iconColor('success')
                    ->actions([Action::make('view')->label('দেখুন')->url(OrderResource::getUrl('view', ['record' => $order], panel: 'admin'))->markAsRead()])
                    ->sendToDatabase($staff);
            }
        }
    }
}

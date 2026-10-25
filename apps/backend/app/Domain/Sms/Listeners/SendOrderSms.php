<?php

namespace App\Domain\Sms\Listeners;

use App\Domain\Order\Enums\OrderSource;
use App\Domain\Order\Events\OrderPlaced;
use App\Domain\Order\Events\OrderStatusChanged;
use App\Domain\Sms\SmsSender;

/** Order events → SMS. Which ones actually go out is set per template in admin. */
class SendOrderSms
{
    public function __construct(private SmsSender $sms) {}

    public function placed(OrderPlaced $event): void
    {
        if ($event->notify) {
            $this->sms->notifyCustomer('order_placed', $event->order);
        }

        // Staff entered it themselves; the new-order alert is only for storefront orders.
        if ($event->order->source === OrderSource::Web) {
            $this->sms->notifyAdmins('admin_new_order', $event->order);
        }
    }

    public function statusChanged(OrderStatusChanged $event): void
    {
        $this->sms->notifyCustomer($event->to->value, $event->order);
    }
}

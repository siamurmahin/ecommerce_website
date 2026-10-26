<?php

namespace App\Domain\WhatsApp\Listeners;

use App\Domain\Order\Enums\OrderSource;
use App\Domain\Order\Events\OrderPlaced;
use App\Domain\Order\Events\OrderStatusChanged;
use App\Domain\WhatsApp\WhatsAppSender;

/** Order events → WhatsApp, alongside SMS. Which ones go out is set per template in admin. */
class SendOrderWhatsApp
{
    public function __construct(private WhatsAppSender $whatsapp) {}

    public function placed(OrderPlaced $event): void
    {
        if ($event->notify) {
            $this->whatsapp->notifyCustomer('order_placed', $event->order);
        }
        if ($event->order->source === OrderSource::Web) {
            $this->whatsapp->notifyAdmins('admin_new_order', $event->order);
        }
    }

    public function statusChanged(OrderStatusChanged $event): void
    {
        $this->whatsapp->notifyCustomer($event->to->value, $event->order);
    }
}

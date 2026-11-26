<?php

namespace App\Domain\Order\Listeners;

use App\Domain\Order\Events\OrderPlaced;
use App\Domain\Order\Events\OrderStatusChanged;
use App\Domain\Order\Models\Order;
use App\Mail\CustomerOrderMail;
use App\Settings\NotificationSettings;
use Illuminate\Support\Facades\Mail;

/** Order events → an email to the customer, when they left an address and the shop has that event switched on. */
class EmailCustomerAboutOrder
{
    public function __construct(private NotificationSettings $settings) {}

    public function placed(OrderPlaced $event): void
    {
        // Staff chose not to notify the customer (admin orders).
        if ($event->notify) {
            $this->send($event->order, 'placed');
        }
    }

    public function statusChanged(OrderStatusChanged $event): void
    {
        $this->send($event->order, $event->to->value);
    }

    private function send(Order $order, string $event): void
    {
        $email = trim((string) $order->customer_email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $this->settings->emailsCustomerOn($event)) {
            return;
        }

        Mail::to($email, $order->customer_name)->queue(new CustomerOrderMail($order, $event));
    }
}

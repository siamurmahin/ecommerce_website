<?php

namespace App\Domain\Fraud\Listeners;

use App\Domain\Fraud\Jobs\CheckPhone;
use App\Domain\Order\Events\OrderPlaced;
use App\Settings\CourierSettings;

final class CheckNewOrder
{
    public function __construct(private CourierSettings $settings) {}

    public function handle(OrderPlaced $event): void
    {
        if ($this->settings->fraud_auto_check) {
            CheckPhone::dispatch($event->order->customer_phone);
        }
    }
}

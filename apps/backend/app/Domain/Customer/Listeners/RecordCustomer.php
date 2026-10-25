<?php

namespace App\Domain\Customer\Listeners;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Events\OrderPlaced;

/** Every phone that orders becomes a customer record, so staff see them all and sign-in finds their history. */
class RecordCustomer
{
    public function handle(OrderPlaced $event): void
    {
        Customer::firstOrCreate(['phone' => $event->order->customer_phone], ['name' => $event->order->customer_name]);
    }
}

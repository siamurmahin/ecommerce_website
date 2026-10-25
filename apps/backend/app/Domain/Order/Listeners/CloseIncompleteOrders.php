<?php

namespace App\Domain\Order\Listeners;

use App\Domain\Order\Enums\IncompleteStatus;
use App\Domain\Order\Events\OrderPlaced;
use App\Domain\Order\Models\IncompleteOrder;

/** Once the same phone orders (on the site or by phone), its open leads are done. */
final class CloseIncompleteOrders
{
    public function handle(OrderPlaced $event): void
    {
        IncompleteOrder::pending()
            ->where('customer_phone', $event->order->customer_phone)
            ->where('last_activity_at', '>=', now()->subDays(14))
            ->update(['status' => IncompleteStatus::Converted, 'order_id' => $event->order->id]);
    }
}

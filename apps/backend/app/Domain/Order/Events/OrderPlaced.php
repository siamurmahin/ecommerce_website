<?php

namespace App\Domain\Order\Events;

use App\Domain\Order\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** A new order was created (storefront checkout or admin). Not fired for a retried submit. */
class OrderPlaced
{
    use Dispatchable;

    /** @param  bool  $notify  false when staff chose not to text the customer (admin orders) */
    public function __construct(public Order $order, public bool $notify = true) {}
}

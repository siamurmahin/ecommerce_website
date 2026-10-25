<?php

namespace App\Domain\Order\Events;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(public Order $order, public OrderStatus $from, public OrderStatus $to) {}
}

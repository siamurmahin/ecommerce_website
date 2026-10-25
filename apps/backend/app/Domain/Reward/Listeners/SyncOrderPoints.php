<?php

namespace App\Domain\Reward\Listeners;

use App\Domain\Order\Events\OrderStatusChanged;
use App\Domain\Reward\PointLedger;

/** Status changes settle earned, redeemed and referral points (PlaceOrder takes redeemed points itself). */
class SyncOrderPoints
{
    public function __construct(private PointLedger $ledger) {}

    public function handle(OrderStatusChanged $event): void
    {
        $this->ledger->syncOrder($event->order);
    }
}

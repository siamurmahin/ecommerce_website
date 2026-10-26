<?php

namespace App\Domain\Finance\Support;

use App\Domain\Order\Models\Order;
use App\Domain\Order\Support\Quote;
use App\Domain\Order\Support\QuoteLine;
use App\Settings\CostSettings;

/**
 * What the shop itself pays the courier for an order. When the customer pays the delivery charge it passes
 * straight to the courier, so the shop's cost is 0. When delivery is free the shop pays: for a free-delivery
 * product its own amount (or the zone's charge) if "owner pays" is on; for threshold/coupon free delivery the
 * zone's charge if খরচ সেটিংস says so.
 */
final class DeliveryCost
{
    public function __construct(private readonly CostSettings $settings) {}

    public function for(Quote $quote, int $chargedToCustomer): int
    {
        if ($chargedToCustomer > 0) {
            return 0;
        }

        $zoneCharge = (int) ($quote->zone?->charge ?? 0);
        $freeProducts = array_filter($quote->lines, fn (QuoteLine $l) => $l->product?->free_delivery);

        if ($freeProducts !== []) {
            $paid = array_filter($freeProducts, fn (QuoteLine $l) => $l->product?->owner_pays_delivery);

            return $paid === [] ? 0 : max(array_map(fn (QuoteLine $l) => $l->product->delivery_cost ?? $zoneCharge, $paid));
        }

        return $this->settings->owner_pays_free_delivery ? $zoneCharge : 0;
    }

    /** Split the order's delivery cost over its items by value, so the product report can carry each one's share. */
    public static function allocate(Order $order, int $cost): void
    {
        $order->update(['delivery_cost' => $cost]);
        $items = $order->items()->orderBy('id')->get();
        $total = (int) $items->sum('line_total');
        if ($cost === 0 || $items->isEmpty()) {
            return;
        }

        $left = $cost;
        foreach ($items as $i => $item) {
            $share = $i === $items->count() - 1 ? $left : ($total > 0 ? intdiv($cost * $item->line_total, $total) : intdiv($cost, $items->count()));
            $item->update(['delivery_cost' => $share]);
            $left -= $share;
        }
    }
}

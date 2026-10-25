<?php

namespace App\Domain\Order\Support;

use App\Domain\Marketing\Models\OrderBump;
use App\Domain\Shipping\Models\DeliveryZone;
use App\Domain\Shipping\Models\District;

final class Quote
{
    /**
     * @param  list<QuoteLine>  $lines
     * @param  list<Discount>  $discounts
     * @param  list<OrderBump>  $bumps  offers to show at checkout
     */
    public function __construct(
        public readonly array $lines,
        public readonly int $subtotal,
        public readonly ?District $district,
        public readonly ?DeliveryZone $zone,
        public readonly ?int $deliveryCharge, // null until a district is chosen (and not free)
        public readonly bool $freeDelivery,
        public readonly int $freeDeliveryThreshold,
        public readonly array $discounts = [],
        public readonly ?CouponResult $coupon = null,
        public readonly array $bumps = [],
    ) {}

    public function discountTotal(): int
    {
        return min($this->subtotal, array_sum(array_map(fn (Discount $d) => $d->amount, $this->discounts)));
    }

    public function total(): int
    {
        return $this->subtotal - $this->discountTotal() + ($this->deliveryCharge ?? 0);
    }

    public function hasProblems(): bool
    {
        return collect($this->lines)->contains(fn (QuoteLine $l) => $l->problem !== null);
    }
}

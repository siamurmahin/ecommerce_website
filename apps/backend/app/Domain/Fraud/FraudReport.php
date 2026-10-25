<?php

namespace App\Domain\Fraud;

/** Delivery history for one phone number. */
final readonly class FraudReport
{
    /**
     * @param  array<string, array{total:int, delivered:int, cancelled:int}>  $couriers  per-courier breakdown (external checkers)
     */
    public function __construct(
        public int $total,
        public int $delivered,
        public int $cancelled,
        public array $couriers = [],
    ) {}

    /** Delivered share of finished parcels, or null with no history. */
    public function successRatio(): ?int
    {
        $finished = $this->delivered + $this->cancelled;

        return $finished > 0 ? (int) round($this->delivered / $finished * 100) : null;
    }
}

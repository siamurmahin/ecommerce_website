<?php

namespace App\Domain\Courier\Data;

/** One parcel in a courier payout. COD in poisha. */
final readonly class PayoutLine
{
    public function __construct(
        public string $consignmentId,
        public ?string $invoice,
        public int $codAmount,
        public string $rawStatus,
    ) {}
}

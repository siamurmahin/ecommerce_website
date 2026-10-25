<?php

namespace App\Domain\Courier\Data;

final readonly class Booking
{
    public function __construct(
        public string $consignmentId,
        public ?string $trackingCode,
        public string $rawStatus,
        public ?int $deliveryFee = null, // poisha
        public array $payload = [],
    ) {}
}

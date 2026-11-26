<?php

namespace App\Domain\Courier\Data;

use Carbon\CarbonImmutable;

/** Money a courier sent the shop. Amounts in poisha. */
final readonly class Payout
{
    /** @param  list<PayoutLine>  $lines  empty in listings */
    public function __construct(
        public string $id,
        public int $amount,
        public int $charges,
        public int $total,
        public ?string $method,
        public string $status,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $paidAt,
        public array $lines = [],
    ) {}
}

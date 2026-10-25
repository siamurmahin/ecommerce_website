<?php

namespace App\Domain\Order\Support;

/** One line of savings on a quote or order (bundle, coupon, referral code, points). */
final readonly class Discount
{
    public const BUNDLE = 'bundle';

    public const COUPON = 'coupon';

    public const REFERRAL = 'referral';

    public const POINTS = 'points';

    public function __construct(
        public string $type,
        public string $label,
        public int $amount, // poisha
    ) {}

    /** @return array{type:string, label:string, amount:int} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'label' => $this->label, 'amount' => $this->amount];
    }
}

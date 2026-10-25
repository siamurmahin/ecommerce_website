<?php

namespace App\Domain\Courier\Data;

/** What staff confirm in the booking dialog. */
final readonly class Parcel
{
    public function __construct(
        public int $codAmount, // poisha
        public float $weightKg = 0.5,
        public ?string $note = null,
        public int|string|null $areaId = null, // couriers that need their own area id (RedX)
    ) {}

    public function codTaka(): int
    {
        return (int) round($this->codAmount / 100);
    }
}

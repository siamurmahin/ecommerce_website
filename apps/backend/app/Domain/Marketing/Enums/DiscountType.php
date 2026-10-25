<?php

namespace App\Domain\Marketing\Enums;

use Filament\Support\Contracts\HasLabel;

enum DiscountType: string implements HasLabel
{
    case Percent = 'percent';
    case Amount = 'amount';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percent => '% ছাড়',
            self::Amount => 'টাকা ছাড়',
        };
    }

    /** Money taken off $base (poisha); $value is a percent or poisha. Never more than $base. */
    public function discount(int $base, int $value): int
    {
        $off = match ($this) {
            // Rounded to whole taka so prices never show paisa.
            self::Percent => (int) round($base * min($value, 100) / 10000) * 100,
            self::Amount => $value,
        };

        return max(0, min($off, $base));
    }
}

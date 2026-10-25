<?php

namespace App\Domain\Marketing\Enums;

use Filament\Support\Contracts\HasLabel;

enum CouponType: string implements HasLabel
{
    case Percent = 'percent';
    case Amount = 'amount';
    case FreeShipping = 'free_shipping';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percent => '% ছাড়',
            self::Amount => 'নির্দিষ্ট টাকা ছাড়',
            self::FreeShipping => 'ফ্রি ডেলিভারি',
        };
    }
}

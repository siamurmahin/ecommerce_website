<?php

namespace App\Domain\Content\Enums;

use Filament\Support\Contracts\HasLabel;

enum BannerPlacement: string implements HasLabel
{
    case Hero = 'hero';
    case Promo = 'promo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hero => 'হিরো স্লাইডার',
            self::Promo => 'প্রোমো ব্যানার',
        };
    }
}

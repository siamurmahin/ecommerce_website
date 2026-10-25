<?php

namespace App\Domain\Catalog\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasLabel
{
    case Simple = 'simple';
    case Variable = 'variable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Simple => 'সিম্পল (একক)',
            self::Variable => 'ভ্যারিয়েবল (সাইজ/রং)',
        };
    }
}

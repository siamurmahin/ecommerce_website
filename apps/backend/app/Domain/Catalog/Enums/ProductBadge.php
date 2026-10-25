<?php

namespace App\Domain\Catalog\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductBadge: string implements HasColor, HasLabel
{
    case New = 'new';
    case BestSeller = 'best_seller';
    case Hot = 'hot';
    case Featured = 'featured';
    case Limited = 'limited';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'নতুন',
            self::BestSeller => 'বেস্ট সেলার',
            self::Hot => 'হট অফার',
            self::Featured => 'বাছাই করা',
            self::Limited => 'সীমিত',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::BestSeller => 'success',
            self::Hot => 'danger',
            self::Featured => 'primary',
            self::Limited => 'warning',
        };
    }
}

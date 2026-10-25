<?php

namespace App\Domain\Inventory\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockReason: string implements HasColor, HasLabel
{
    case Sale = 'sale';
    case Restock = 'restock'; // order cancelled, returned or deleted
    case Purchase = 'purchase';
    case Adjustment = 'adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sale => 'বিক্রি',
            self::Restock => 'অর্ডার বাতিল/রিটার্ন',
            self::Purchase => 'পারচেজ (মাল এসেছে)',
            self::Adjustment => 'হাতে সমন্বয়',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sale => 'info',
            self::Restock => 'warning',
            self::Purchase => 'success',
            self::Adjustment => 'gray',
        };
    }
}

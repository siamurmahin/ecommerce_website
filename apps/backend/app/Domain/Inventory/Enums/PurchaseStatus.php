<?php

namespace App\Domain\Inventory\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'খসড়া',
            self::Ordered => 'অর্ডার দেওয়া',
            self::Received => 'মাল এসেছে',
            self::Cancelled => 'বাতিল',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Ordered => 'warning',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function editable(): bool
    {
        return in_array($this, [self::Draft, self::Ordered], true);
    }
}

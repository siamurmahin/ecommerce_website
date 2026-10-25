<?php

namespace App\Domain\Order\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Unpaid = 'unpaid';
    /** Customer sent a transaction ID; staff haven't checked it yet. */
    case Pending = 'pending';
    /** Advance (e.g. delivery charge) received, the rest is collected on delivery. */
    case Partial = 'partial';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'অপরিশোধিত',
            self::Pending => 'যাচাই বাকি',
            self::Partial => 'অগ্রিম পরিশোধিত',
            self::Paid => 'পরিশোধিত',
            self::Refunded => 'রিফান্ডেড',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Pending => 'info',
            self::Partial => 'primary',
            self::Paid => 'success',
            self::Refunded => 'gray',
        };
    }
}

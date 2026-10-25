<?php

namespace App\Domain\Order\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case OnHold = 'on_hold';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'পেন্ডিং',
            self::Confirmed => 'কনফার্মড',
            self::Processing => 'প্রসেসিং',
            self::OnHold => 'হোল্ডে',
            self::Shipped => 'শিপড',
            self::Delivered => 'ডেলিভারড',
            self::Cancelled => 'বাতিল',
            self::Returned => 'রিটার্নড',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed, self::Processing => 'info',
            self::OnHold => 'gray',
            self::Shipped => 'primary',
            self::Delivered => 'success',
            self::Cancelled, self::Returned => 'danger',
        };
    }

    /** Stock is back on the shelf for these, so moving in or out of them adjusts inventory. */
    public function releasesStock(): bool
    {
        return in_array($this, [self::Cancelled, self::Returned], true);
    }
}

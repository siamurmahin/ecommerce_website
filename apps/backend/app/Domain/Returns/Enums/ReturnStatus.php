<?php

namespace App\Domain\Returns\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** requested → approved → received (stock back) → completed (refunded / replacement sent); or rejected. */
enum ReturnStatus: string implements HasColor, HasLabel
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Received = 'received';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'অনুরোধ এসেছে',
            self::Approved => 'অনুমোদিত — পণ্য ফেরত আসার অপেক্ষা',
            self::Received => 'পণ্য ফেরত এসেছে',
            self::Completed => 'সম্পন্ন',
            self::Rejected => 'বাতিল',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Approved, self::Received => 'info',
            self::Completed => 'success',
            self::Rejected => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::Approved, self::Received], true);
    }
}

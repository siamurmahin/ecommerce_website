<?php

namespace App\Domain\Order\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IncompleteStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Discarded = 'discarded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'নতুন',
            self::Contacted => 'যোগাযোগ করা হয়েছে',
            self::Converted => 'অর্ডার হয়েছে',
            self::Discarded => 'বাদ',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Contacted => 'info',
            self::Converted => 'success',
            self::Discarded => 'gray',
        };
    }
}

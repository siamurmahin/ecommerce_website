<?php

namespace App\Domain\Returns\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReturnType: string implements HasColor, HasLabel
{
    case Return = 'return';
    case Exchange = 'exchange';

    public function getLabel(): string
    {
        return match ($this) {
            self::Return => 'রিটার্ন (টাকা ফেরত)',
            self::Exchange => 'এক্সচেঞ্জ (বদলে দেওয়া)',
        };
    }

    public function getColor(): string
    {
        return $this === self::Return ? 'danger' : 'info';
    }
}

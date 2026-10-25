<?php

namespace App\Domain\Review\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuestionStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Published = 'published';
    case Hidden = 'hidden';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'উত্তর বাকি',
            self::Published => 'প্রকাশিত',
            self::Hidden => 'লুকানো',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Published => 'success',
            self::Hidden => 'gray',
        };
    }
}

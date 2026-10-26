<?php

namespace App\Domain\Returns\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReturnReason: string implements HasLabel
{
    case Damaged = 'damaged';
    case WrongItem = 'wrong_item';
    case Size = 'size';
    case NotAsDescribed = 'not_as_described';
    case Quality = 'quality';
    case ChangedMind = 'changed_mind';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Damaged => 'ভাঙা / নষ্ট এসেছে',
            self::WrongItem => 'ভুল পণ্য এসেছে',
            self::Size => 'সাইজ / মাপ মেলেনি',
            self::NotAsDescribed => 'ছবি/বর্ণনার সাথে মেলেনি',
            self::Quality => 'মান ভালো না',
            self::ChangedMind => 'মত বদলেছি',
            self::Other => 'অন্য কারণ',
        };
    }
}

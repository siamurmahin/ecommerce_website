<?php

namespace App\Domain\Content\Enums;

use Filament\Support\Contracts\HasLabel;

enum MenuLocation: string implements HasLabel
{
    case Header = 'header';
    case FooterHelp = 'footer_help';
    case FooterCompany = 'footer_company';

    public function getLabel(): string
    {
        return match ($this) {
            self::Header => 'হেডার ও মোবাইল মেনু',
            self::FooterHelp => 'ফুটার — সাহায্য',
            self::FooterCompany => 'ফুটার — প্রতিষ্ঠান',
        };
    }
}

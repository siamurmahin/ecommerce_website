<?php

namespace App\Domain\Order\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * bKash/Nagad/Rocket are manual for now: the customer sends money to the shop's number and types
 * the transaction ID, staff verify it. Automatic gateways (SSLCommerz, bKash PGW) come later as drivers.
 */
enum PaymentMethod: string implements HasLabel
{
    case CashOnDelivery = 'cod';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Rocket = 'rocket';

    public function getLabel(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'ক্যাশ অন ডেলিভারি',
            self::Bkash => 'বিকাশ',
            self::Nagad => 'নগদ',
            self::Rocket => 'রকেট',
        };
    }

    public function isWallet(): bool
    {
        return $this !== self::CashOnDelivery;
    }

    /** @return list<self> */
    public static function wallets(): array
    {
        return array_values(array_filter(self::cases(), fn (self $m) => $m->isWallet()));
    }
}

<?php

namespace App\Settings;

use App\Domain\Order\Enums\PaymentMethod;
use Spatie\LaravelSettings\Settings;

class PaymentSettings extends Settings
{
    public bool $cod_enabled;

    /** 'none' or 'delivery': COD customers pay the delivery charge in advance by mobile wallet. */
    public string $cod_advance;

    /** Manual mobile-wallet accounts keyed by PaymentMethod value: enabled, number, type (see ACCOUNT_TYPES), instructions. */
    public array $wallets;

    public const ACCOUNT_TYPES = ['personal' => 'পার্সোনাল (Send Money)', 'agent' => 'এজেন্ট (Cash Out)', 'merchant' => 'মার্চেন্ট (Payment)'];

    public static function group(): string
    {
        return 'payment';
    }

    /** Wallets switched on with a number to pay to. @return list<PaymentMethod> */
    public function activeWallets(): array
    {
        return array_values(array_filter(
            PaymentMethod::wallets(),
            fn (PaymentMethod $m) => ($this->wallets[$m->value]['enabled'] ?? false) && filled($this->wallets[$m->value]['number'] ?? null),
        ));
    }

    public function isAvailable(PaymentMethod $method): bool
    {
        return $method === PaymentMethod::CashOnDelivery ? $this->cod_enabled : in_array($method, $this->activeWallets(), true);
    }

    public function requiresDeliveryAdvance(): bool
    {
        return $this->cod_advance === 'delivery' && $this->activeWallets() !== [];
    }
}

<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Costs that apply to every product, edited under সেটিংস → খরচ; used only for the profit report. */
class CostSettings extends Settings
{
    /** Per-unit costs added to every product that keeps "shared costs" on: [{label, amount}], amount in poisha. */
    public array $shared;

    /** When an order ships free (threshold, coupon), the shop pays the zone's delivery charge itself. */
    public bool $owner_pays_free_delivery;

    public static function group(): string
    {
        return 'costs';
    }

    /** Sum of the shared per-unit costs, poisha. */
    public function sharedPerUnit(): int
    {
        return (int) array_sum(array_map(fn (array $c) => (int) ($c['amount'] ?? 0), $this->shared));
    }
}

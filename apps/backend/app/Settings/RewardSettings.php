<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Loyalty points and referrals, edited under মার্কেটিং → রিওয়ার্ড ও রেফারেল. Money is poisha. */
class RewardSettings extends Settings
{
    public bool $points_enabled;

    /** Points earned per ৳100 of a delivered order's products (after discounts, before delivery). */
    public int $earn_per_100;

    /** Worth of one point at checkout, in poisha (100 = ৳1). */
    public int $point_value;

    /** Smallest number of points that can be used at once. */
    public int $min_redeem;

    /** Points may pay at most this share of the products' price. */
    public int $max_redeem_percent;

    public bool $referral_enabled;

    /** Points the referrer gets when the friend's first order is delivered. */
    public int $referrer_points;

    /** Discount on the friend's first order (poisha). */
    public int $referee_discount;

    /** Products' total the friend's first order needs for the discount (poisha). */
    public int $referee_min_subtotal;

    public static function group(): string
    {
        return 'rewards';
    }

    public function pointsFor(int $amount): int
    {
        return $this->points_enabled ? intdiv(max(0, $amount) * $this->earn_per_100, 10000) : 0;
    }

    /** Largest number of points usable on products worth `$amount` with `$balance` available. */
    public function redeemable(int $balance, int $amount): int
    {
        if (! $this->points_enabled || $this->point_value <= 0) {
            return 0;
        }
        $cap = intdiv(intdiv($amount * min(100, $this->max_redeem_percent), 100), $this->point_value);
        $points = min($balance, $cap);

        return $points >= $this->min_redeem ? $points : 0;
    }
}

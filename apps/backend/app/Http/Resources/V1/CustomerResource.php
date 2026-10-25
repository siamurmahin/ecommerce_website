<?php

namespace App\Http\Resources\V1;

use App\Domain\Customer\Models\Customer;
use App\Settings\RewardSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in customer, with their saved addresses (default first).
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone' => $this->phone,
            'name' => $this->name,
            'email' => $this->email,
            'addresses' => CustomerAddressResource::collection($this->addresses),
            /** Loyalty points and referral; parts the shop has switched off are null. Money is poisha. */
            'rewards' => $this->rewards(),
        ];
    }

    /**
     * @return array{
     *   points: array{balance: int, point_value: int, min_redeem: int, max_redeem_percent: int, earn_per_100: int}|null,
     *   referral: array{code: string, link: string, friend_discount: int, friend_min_subtotal: int, your_points: int}|null
     * }
     */
    private function rewards(): array
    {
        $settings = app(RewardSettings::class);

        return [
            'points' => $settings->points_enabled ? [
                'balance' => (int) $this->points_balance,
                'point_value' => (int) $settings->point_value,
                'min_redeem' => (int) $settings->min_redeem,
                'max_redeem_percent' => (int) $settings->max_redeem_percent,
                'earn_per_100' => (int) $settings->earn_per_100,
            ] : null,
            'referral' => $settings->referral_enabled ? [
                'code' => $code = $this->resource->referralCode(),
                'link' => rtrim((string) config('shop.storefront_url'), '/').'/?ref='.$code,
                'friend_discount' => (int) $settings->referee_discount,
                'friend_min_subtotal' => (int) $settings->referee_min_subtotal,
                'your_points' => (int) $settings->referrer_points,
            ] : null,
        ];
    }
}

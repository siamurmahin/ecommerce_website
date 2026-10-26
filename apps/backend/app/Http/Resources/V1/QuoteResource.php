<?php

namespace App\Http\Resources\V1;

use App\Domain\Order\Support\Quote;
use App\Settings\RewardSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Server-priced cart. All money is integer poisha.
 *
 * @mixin Quote
 */
class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'lines' => QuoteLineResource::collection($this->lines),
            'subtotal' => $this->subtotal,
            'zone' => $this->zone ? new DeliveryZoneResource($this->zone) : null,
            /** @var int|null */
            'delivery_charge' => $this->deliveryCharge,
            'free_delivery' => $this->freeDelivery,
            'free_delivery_threshold' => $this->freeDeliveryThreshold,
            /** Package, bundle, coupon and referral savings, already subtracted from total ('points' only appears on orders). */
            /** @var array<int, array{type: 'package'|'bundle'|'coupon'|'referral'|'points', label: string, amount: int}> */
            'discounts' => array_map(fn ($d) => $d->toArray(), $this->discounts),
            'discount_total' => $this->discountTotal(),
            'coupon' => $this->coupon ? [
                'code' => $this->coupon->code,
                'applied' => $this->coupon->applied(),
                /** @var 'invalid'|'not_started'|'expired'|'used_up'|'min_subtotal'|'not_applicable'|'phone_limit'|null */
                'error' => $this->coupon->error,
                'free_shipping' => $this->coupon->freeShipping,
                /** @var int|null */
                'min_subtotal' => $this->coupon->coupon?->min_subtotal ?? ($this->coupon->referrer ? app(RewardSettings::class)->referee_min_subtotal : null),
            ] : null,
            /** Order bumps on offer for this cart (send back as an item with bump_id to take one). */
            /** @var array<int, array{id: int, product_id: int, title: string, description: string|null, price: int, regular_price: int, name: string, image: string|null}> */
            'bumps' => array_map(fn ($bump) => [
                'id' => $bump->id,
                'product_id' => $bump->product_id,
                'title' => $bump->localized('title'),
                /** @var string|null */
                'description' => $bump->localized('description'),
                'price' => min($bump->price, $bump->product->unitPrice()),
                'regular_price' => $bump->product->unitPrice(),
                'name' => $bump->product->localized('name'),
                /** @var string|null */
                'image' => ($image = $bump->product->images->first()) ? Storage::disk('public')->url($image->path) : null,
            ], $this->bumps),
            'total' => $this->total(),
            'has_problems' => $this->hasProblems(),
        ];
    }
}

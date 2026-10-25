<?php

namespace App\Domain\Order\Support;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\Customer;
use App\Domain\Marketing\Models\Bundle;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\CouponRedemption;
use App\Domain\Marketing\Models\OrderBump;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use App\Settings\RewardSettings;
use App\Settings\ShippingSettings;
use Illuminate\Support\Collection;

/**
 * Prices a cart from the database. The storefront's stored prices are never trusted:
 * this is the single place that decides unit prices (flash sales included), stock,
 * order bumps, bundle and coupon discounts, and the delivery charge.
 */
final class CartPricer
{
    private const MAX_OFFERED_BUMPS = 2;

    public function __construct(private ShippingSettings $shipping) {}

    /**
     * @param  list<array{product_id:int, variant_id?:int|null, quantity:int, bump_id?:int|null}>  $items
     * @param  string|null  $phone  needed for per-phone coupon limits (known at order time)
     */
    public function quote(array $items, ?int $districtId = null, ?string $couponCode = null, ?string $phone = null): Quote
    {
        $products = Product::visible()
            ->with(['images', 'variants.attributeValues.attribute', 'category'])
            ->whereIn('id', array_column($items, 'product_id'))
            ->get()
            ->keyBy('id');

        [$bumpItems, $regularItems] = collect($items)->partition(fn ($item) => filled($item['bump_id'] ?? null));

        $lines = $regularItems->map(fn ($item) => $this->line($item, $products))->values()->all();
        $priced = array_values(array_filter($lines, fn (QuoteLine $l) => $l->problem !== QuoteLine::UNAVAILABLE));
        $regularSubtotal = array_sum(array_map(fn (QuoteLine $l) => $l->lineTotal(), $priced));
        $cartProductIds = array_map(fn (QuoteLine $l) => $l->productId, $priced);

        // Bumps: only honoured when still on offer for this cart; otherwise silently left out.
        $bumps = OrderBump::active()->orderBy('sort_order')->get();
        foreach ($bumpItems as $item) {
            $bump = $bumps->firstWhere('id', (int) $item['bump_id']);
            $product = $products->get($item['product_id']);
            if ($bump && $product && $bump->product_id === $product->id && ! $product->isVariable()
                && $bump->eligibleFor($cartProductIds, $regularSubtotal) && $product->stock > 0) {
                $lines[] = $priced[] = new QuoteLine(
                    productId: $product->id, variantId: null, quantity: 1, product: $product, variant: null,
                    unitPrice: min($bump->price, $product->unitPrice()), available: $product->stock, problem: null, bumpId: $bump->id,
                );
            }
        }

        $subtotal = array_sum(array_map(fn (QuoteLine $l) => $l->lineTotal(), $priced));
        $discounts = $this->bundleDiscounts(array_filter($priced, fn (QuoteLine $l) => ! $l->bumpId));
        $bundleTotal = array_sum(array_map(fn (Discount $d) => $d->amount, $discounts));

        $coupon = filled($couponCode) ? $this->coupon($couponCode, $priced, $regularSubtotal, $regularSubtotal - $bundleTotal, $phone) : null;
        if ($coupon?->applied() && $coupon->discount > 0) {
            $discounts[] = new Discount($coupon->referrer ? Discount::REFERRAL : Discount::COUPON, $coupon->code, $coupon->discount);
        }

        $threshold = $this->shipping->free_delivery_threshold;
        $free = collect($priced)->contains(fn (QuoteLine $l) => $l->product->free_delivery)
            || ($threshold > 0 && $subtotal >= $threshold)
            || ($coupon?->applied() && $coupon->freeShipping);

        $district = $districtId ? District::with('deliveryZone')->find($districtId) : null;
        $zone = $district?->resolveZone();

        $inCart = collect($priced)->pluck('productId')->all();
        $offered = $bumps
            ->filter(fn (OrderBump $b) => ! in_array($b->product_id, $inCart, true) && $b->eligibleFor($cartProductIds, $regularSubtotal))
            ->load('product.images')
            ->filter(fn (OrderBump $b) => $b->product && $b->product->is_active && ! $b->product->isVariable() && $b->product->stock > 0)
            ->take(self::MAX_OFFERED_BUMPS)
            ->values()->all();

        return new Quote(
            lines: $lines,
            subtotal: $subtotal,
            district: $district,
            zone: $zone,
            deliveryCharge: $free ? 0 : $zone?->charge,
            freeDelivery: $free,
            freeDeliveryThreshold: $threshold,
            discounts: $discounts,
            coupon: $coupon,
            bumps: $offered,
        );
    }

    /** @param  Collection<int, Product>  $products */
    private function line(array $item, Collection $products): QuoteLine
    {
        $product = $products->get($item['product_id']);
        $variantId = $item['variant_id'] ?? null;
        $variant = $variantId ? $product?->variants->firstWhere('id', $variantId) : null;
        $quantity = (int) $item['quantity'];

        $sellable = $product && ($product->isVariable()
            ? $variant && $variant->is_active
            : $variantId === null);

        $available = ! $sellable ? 0 : ($variant ? $variant->stock : $product->stock);

        return new QuoteLine(
            productId: (int) $item['product_id'],
            variantId: $variantId,
            quantity: $quantity,
            product: $product,
            variant: $variant,
            unitPrice: $sellable ? $product->unitPrice($variant) : 0,
            available: max(0, $available),
            problem: match (true) {
                ! $sellable || $available <= 0 => QuoteLine::UNAVAILABLE,
                $available < $quantity => QuoteLine::INSUFFICIENT_STOCK,
                default => null,
            },
        );
    }

    /**
     * Each complete set of a bundle's items in the cart earns its discount. Units used by one bundle
     * aren't reused by another; bundles are tried biggest saving first.
     *
     * @param  array<QuoteLine>  $lines
     * @return list<Discount>
     */
    private function bundleDiscounts(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $remaining = [];
        $cheapest = [];
        foreach ($lines as $line) {
            $remaining[$line->productId] = ($remaining[$line->productId] ?? 0) + $line->quantity;
            $cheapest[$line->productId] = min($cheapest[$line->productId] ?? PHP_INT_MAX, $line->unitPrice);
        }

        $candidates = Bundle::active()->with('items')
            ->whereHas('items', fn ($q) => $q->whereIn('product_id', array_keys($remaining)))
            ->get()
            ->filter(fn (Bundle $b) => $b->items->isNotEmpty() && $b->items->every(fn ($i) => isset($remaining[$i->product_id])))
            ->map(function (Bundle $b) use ($cheapest) {
                $setPrice = $b->items->sum(fn ($i) => $cheapest[$i->product_id] * $i->quantity);

                return [$b, $b->discount_type->discount($setPrice, $b->discount_value)];
            })
            ->sortByDesc(fn ($pair) => $pair[1]);

        $discounts = [];
        foreach ($candidates as [$bundle, $perSet]) {
            $sets = $bundle->items->min(fn ($i) => intdiv($remaining[$i->product_id], max(1, $i->quantity)));
            if ($sets < 1 || $perSet <= 0) {
                continue;
            }

            foreach ($bundle->items as $item) {
                $remaining[$item->product_id] -= $sets * $item->quantity;
            }
            $discounts[] = new Discount(Discount::BUNDLE, $bundle->localized('name'), $perSet * $sets);
        }

        return $discounts;
    }

    /** @param  list<QuoteLine>  $lines */
    private function coupon(string $code, array $lines, int $regularSubtotal, int $afterBundles, ?string $phone): CouponResult
    {
        $coupon = Coupon::findByCode($code);
        $code = strtoupper(trim($code));
        $fail = fn (string $error) => new CouponResult($code, $coupon, $error);

        if (! $coupon) {
            return $this->referral($code, $afterBundles, $regularSubtotal, $phone);
        }
        if (! $coupon->is_active) {
            return new CouponResult($code, null, CouponResult::INVALID);
        }
        if ($coupon->starts_at?->isFuture()) {
            return $fail(CouponResult::NOT_STARTED);
        }
        if ($coupon->ends_at?->isPast()) {
            return $fail(CouponResult::EXPIRED);
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return $fail(CouponResult::USED_UP);
        }
        if ($phone && $coupon->per_phone_limit !== null
            && CouponRedemption::where('coupon_id', $coupon->id)->where('phone', $phone)->count() >= $coupon->per_phone_limit) {
            return $fail(CouponResult::PHONE_LIMIT);
        }
        if ($regularSubtotal < $coupon->min_subtotal) {
            return $fail(CouponResult::MIN_SUBTOTAL);
        }

        $base = $afterBundles;
        if ($coupon->isLimitedToItems()) {
            $productIds = array_map('intval', $coupon->product_ids ?? []);
            $categoryIds = array_map('intval', $coupon->category_ids ?? []);
            $base = collect($lines)
                ->filter(fn (QuoteLine $l) => ! $l->bumpId && (in_array($l->productId, $productIds, true)
                    || in_array((int) $l->product->category_id, $categoryIds, true)
                    || in_array((int) $l->product->category?->parent_id, $categoryIds, true)))
                ->sum(fn (QuoteLine $l) => $l->lineTotal());

            if ($base === 0) {
                return $fail(CouponResult::NOT_APPLICABLE);
            }
        }

        $discount = match ($coupon->type->value) {
            'percent' => (int) round($base * min($coupon->value, 100) / 10000) * 100, // whole taka
            'amount' => $coupon->value,
            default => 0,
        };
        if ($coupon->max_discount) {
            $discount = min($discount, $coupon->max_discount);
        }

        return new CouponResult($code, $coupon, null, max(0, min($discount, $base)), $coupon->type->value === 'free_shipping');
    }

    /** A friend's referral code typed in the coupon box: a fixed discount on a new customer's first order. */
    private function referral(string $code, int $base, int $regularSubtotal, ?string $phone): CouponResult
    {
        $rewards = app(RewardSettings::class);
        $referrer = $rewards->referral_enabled ? Customer::firstWhere('referral_code', $code) : null;

        if (! $referrer || $referrer->is_blocked) {
            return new CouponResult($code, null, CouponResult::INVALID);
        }
        if ($phone && ($phone === $referrer->phone || Order::where('customer_phone', $phone)->where('status', '!=', OrderStatus::Cancelled)->exists())) {
            return new CouponResult($code, null, CouponResult::REFERRAL_FIRST_ORDER, referrer: $referrer);
        }
        if ($regularSubtotal < $rewards->referee_min_subtotal) {
            return new CouponResult($code, null, CouponResult::MIN_SUBTOTAL, referrer: $referrer);
        }

        return new CouponResult($code, null, null, max(0, min($rewards->referee_discount, $base)), referrer: $referrer);
    }
}

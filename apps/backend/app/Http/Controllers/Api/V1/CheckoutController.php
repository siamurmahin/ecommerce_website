<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Marketing\Models\LandingPage;
use App\Domain\Order\Enums\IncompleteStatus;
use App\Domain\Order\Enums\PaymentMethod;
use App\Domain\Order\Models\IncompleteOrder;
use App\Domain\Order\Support\CartPricer;
use App\Domain\Shipping\Models\DeliveryZone;
use App\Domain\Shipping\Models\District;
use App\Domain\Shipping\Models\Thana;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\QuoteCartRequest;
use App\Http\Requests\Api\V1\SaveDraftRequest;
use App\Http\Resources\V1\DeliveryZoneResource;
use App\Http\Resources\V1\QuoteLineResource;
use App\Http\Resources\V1\QuoteResource;
use App\Settings\GiftSettings;
use App\Settings\PaymentSettings;
use App\Settings\ShippingSettings;
use App\Settings\SmsSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CheckoutController extends Controller
{
    /** Everything the checkout form needs: districts grouped by division, zones, payment methods. */
    public function options(ShippingSettings $shipping, PaymentSettings $payment, GiftSettings $gift): array
    {
        $fallback = DeliveryZone::fallback();
        $zones = DeliveryZone::active()->orderBy('sort_order')->get();

        $divisions = District::with(['thanas' => fn ($q) => $q->active()->orderBy('name')])->orderBy('id')->get()
            ->groupBy('division_en')
            ->map(fn ($districts) => [
                'name' => $districts->first()->localized('division'),
                'districts' => $districts->map(fn (District $d) => [
                    'id' => $d->id,
                    'name' => $d->localized('name'),
                    'zone_id' => $zones->contains('id', $d->delivery_zone_id) ? $d->delivery_zone_id : $fallback?->id,
                    'thanas' => $d->thanas->map(fn (Thana $t) => ['id' => $t->id, 'name' => $t->localized('name')])->values(),
                ])->values(),
            ])->values()->all();

        return [
            'zones' => DeliveryZoneResource::collection($zones),
            /** @var array<int, array{name: string, districts: array<int, array{id: int, name: string, zone_id: int|null, thanas: array<int, array{id: int, name: string}>}>}> */
            'divisions' => $divisions,
            'free_delivery_threshold' => $shipping->free_delivery_threshold,
            'capture_incomplete' => $shipping->capture_incomplete_orders,
            /** Methods the shop has switched on; wallets carry the number to send money to. @var array<int, array{code: string, number: string|null, account_type: string|null, instructions: string|null}> */
            'payment_methods' => collect(PaymentMethod::cases())
                ->filter(fn (PaymentMethod $m) => $payment->isAvailable($m))
                ->map(fn (PaymentMethod $m) => [
                    'code' => $m->value,
                    'number' => $m->isWallet() ? $payment->wallets[$m->value]['number'] : null,
                    'account_type' => $m->isWallet() ? ($payment->wallets[$m->value]['type'] ?? 'personal') : null,
                    'instructions' => $m->isWallet() ? ($payment->wallets[$m->value]['instructions'] ?? null) : null,
                ])->values()->all(),
            /** "delivery": cash-on-delivery orders pay the delivery charge first by one of the wallets. */
            'cod_advance' => $payment->requiresDeliveryAdvance() ? 'delivery' : 'none',
            /** Gift wrap (price in poisha, added to the total) and gift note, when switched on. */
            'gift' => [
                'wrap' => $gift->wrap_enabled,
                'wrap_price' => $gift->wrap_price,
                'note' => $gift->note_enabled,
            ],
        ];
    }

    /** Re-price a cart against current prices and stock; add district_id to include delivery. */
    public function quote(QuoteCartRequest $request, CartPricer $pricer): QuoteResource
    {
        $package = LandingPage::find($request->validated('landing_page_id'))?->package($request->validated('package'));

        return new QuoteResource($pricer->quote($request->validated('items'), $request->validated('district_id'), $request->validated('coupon_code'), package: $package));
    }

    /**
     * Save what the customer has typed so far as an incomplete order (needs a valid phone).
     * Called again as they keep typing; the same draft_id updates the same row.
     */
    public function draft(SaveDraftRequest $request, CartPricer $pricer, ShippingSettings $shipping): Response
    {
        if (! $shipping->capture_incomplete_orders) {
            return response()->noContent();
        }

        $data = $request->validated();
        $draft = IncompleteOrder::firstOrNew(['draft_id' => $data['draft_id']]);

        // Converted or handled leads are left as they are.
        if ($draft->exists && $draft->status !== IncompleteStatus::Open) {
            return response()->noContent();
        }

        $draft->fill([
            'customer_name' => $data['name'] ?? null,
            'customer_phone' => $data['phone'],
            'district_id' => $data['district_id'] ?? null,
            'thana_id' => $data['thana_id'] ?? null,
            'address' => $data['address'] ?? null,
            'customer_note' => $data['note'] ?? null,
            'items' => $data['items'],
            'subtotal' => $pricer->quote($data['items'], null)->subtotal,
            'utm' => $data['utm'] ?? null,
            'locale' => app()->getLocale(),
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'last_activity_at' => now(),
        ])->save();

        return response()->noContent();
    }

    /**
     * The cart and details from a reminder SMS link (`/r/{token}`), re-priced now.
     *
     * @response array{name: string|null, phone: string, district_id: int|null, thana_id: int|null, address: string|null, coupon: string|null, lines: list<array<string, mixed>>}
     */
    public function recover(string $token, CartPricer $pricer, SmsSettings $sms): JsonResponse
    {
        $draft = IncompleteOrder::where('recovery_token', $token)->where('last_activity_at', '>=', now()->subDays(14))->firstOrFail();
        $draft->recovered_at ??= now();
        $draft->save();

        $quote = $pricer->quote($draft->items ?? [], null);

        return response()->json([
            'name' => $draft->customer_name,
            'phone' => $draft->customer_phone,
            'district_id' => $draft->district_id,
            'thana_id' => $draft->thana_id,
            'address' => $draft->address,
            'coupon' => $sms->recovery_coupon,
            'lines' => QuoteLineResource::collection(array_values(array_filter($quote->lines, fn ($l) => $l->problem !== 'unavailable')))->resolve(),
        ]);
    }
}

<?php

namespace App\Http\Resources\V1;

use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Customer-facing order (thank-you page). Money is integer poisha.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => $this->status->getLabel(),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->payment_status->getLabel(),
            'payment_wallet' => $this->payment_wallet,
            /** @var string|null */
            'payment_trx_id' => $this->payment_trx_id,
            /** Poisha the customer sent in advance (0 for plain cash on delivery). */
            'advance_amount' => $this->advance_amount,
            /**
             * Still to pay on delivery (assuming a pending advance checks out).
             *
             * @var int
             */
            'due_amount' => $this->payment_status === PaymentStatus::Pending ? max(0, $this->total - $this->advance_amount) : $this->dueOnDelivery(),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'address' => $this->address,
            /** @var string|null */
            'district' => $this->district?->localized('name') ?? $this->district_name,
            'thana' => $this->thana?->localized('name') ?? $this->thana_name,
            /** @var string|null */
            'delivery_zone' => $this->deliveryZone?->localized('name') ?? $this->delivery_zone_name,
            /** @var int|null */
            'eta_min_days' => $this->deliveryZone?->eta_min_days,
            /** @var int|null */
            'eta_max_days' => $this->deliveryZone?->eta_max_days,
            'items' => $this->items->map(fn ($item) => [
                /** @var int|null */
                'product_id' => $item->product_id,
                'name' => $item->product?->localized('name') ?? $item->name,
                /** @var string|null */
                'variant_label' => $item->variant?->label() ?? $item->variant_label,
                /** @var string|null */
                'image' => $item->image ? Storage::disk('public')->url($item->image) : null,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
            ]),
            'subtotal' => $this->subtotal,
            'delivery_charge' => $this->delivery_charge,
            'discount' => $this->discount,
            'total' => $this->total,
            /** Shared with browser pixel events for de-duplication. */
            /** @var string|null */
            'event_id' => $this->event_id,
            /** Status timeline, oldest first (only on the order page). */
            'history' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->sortBy('id')->values()->map(fn ($h) => [
                'status' => $h->to,
                'at' => $h->created_at->toIso8601String(),
            ])),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

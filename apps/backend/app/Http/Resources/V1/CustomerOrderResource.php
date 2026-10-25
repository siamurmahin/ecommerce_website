<?php

namespace App\Http\Resources\V1;

use App\Domain\Order\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * One row of the account's order history.
 *
 * @mixin Order
 */
class CustomerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $first = $this->items->first();

        return [
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => $this->status->getLabel(),
            'total' => $this->total,
            'items_count' => (int) $this->items->sum('quantity'),
            /** First item's name, e.g. for "কাবলি পাঞ্জাবি + ২টি". */
            /** @var string|null */
            'title' => $first?->product?->localized('name') ?? $first?->name,
            /** @var string|null */
            'image' => $first?->image ? Storage::disk('public')->url($first->image) : null,
            /** Opens /order/{number}?token=… */
            'access_token' => $this->resource->access_token,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

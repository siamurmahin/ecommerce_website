<?php

namespace App\Http\Resources\V1;

use App\Domain\Marketing\Models\Bundle;
use App\Domain\Marketing\Models\BundleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Buy-together offer. Prices are for one complete set, in poisha.
 *
 * @mixin Bundle
 */
class BundleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $regular = (int) $this->items->sum(fn (BundleItem $i) => $i->product->displayPrice() * $i->quantity);
        $saving = $this->discount_type->discount($regular, $this->discount_value);

        return [
            'id' => $this->id,
            'name' => $this->localized('name'),
            'regular_price' => $regular,
            'price' => $regular - $saving,
            'saving' => $saving,
            'items' => $this->items->map(fn (BundleItem $i) => [
                'quantity' => (int) $i->quantity,
                'product' => new ProductCardResource($i->product),
            ])->values(),
        ];
    }
}

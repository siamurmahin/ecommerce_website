<?php

namespace App\Http\Resources\V1;

use App\Domain\Order\Support\QuoteLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * One server-priced cart line. Money is integer poisha.
 *
 * @mixin QuoteLine
 */
class QuoteLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->productId,
            /** @var int|null */
            'variant_id' => $this->variantId,
            /** @var string|null */
            'name' => $this->product?->localized('name'),
            /** @var string|null */
            'slug' => $this->product?->slug,
            /** @var string|null */
            'variant_label' => $this->variant?->label(),
            /** @var string|null */
            'image' => ($path = $this->imagePath()) ? Storage::disk('public')->url($path) : null,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'available' => $this->available,
            'line_total' => $this->lineTotal(),
            /** Set when this line is a checkout order bump. */
            /** @var int|null */
            'bump_id' => $this->bumpId,
            /** @var 'unavailable'|'insufficient_stock'|null */
            'problem' => $this->problem,
        ];
    }
}

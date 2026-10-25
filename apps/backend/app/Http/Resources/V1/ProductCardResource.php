<?php

namespace App\Http\Resources\V1;

use App\Domain\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Compact product for grids and carousels. Prices are integer poisha.
 *
 * @mixin Product
 */
class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->whenLoaded('images', fn () => $this->images, collect());
        $base = $this->basePrice();
        $price = $this->salePrice($base);
        $compare = $this->isVariable() && $this->relationLoaded('variants')
            ? $this->variants->where('is_active', true)->firstWhere('price', $base)?->compare_price ?? $this->compare_price
            : $this->compare_price;
        // During a flash sale the regular price is what's crossed out (or the higher compare price).
        $compare = $price < $base ? max((int) $compare, $base) : (int) $compare;
        $flash = $this->flashSaleItem();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->localized('name'),
            'type' => $this->type,
            'price' => $price,
            /** @var int|null */
            'compare_price' => $compare > $price ? $compare : null,
            'price_from' => $this->isVariable(),
            'badge' => $this->badge,
            /** Running flash sale for this product. */
            'flash_sale' => $flash ? [
                'name' => $flash->flashSale->localized('name'),
                'ends_at' => $flash->flashSale->ends_at->toIso8601String(),
            ] : null,
            'in_stock' => ($stock = $this->totalStock()) > 0,
            /** Units left (sum of active variants for variable products). */
            'stock' => $stock,
            /** @var bool */
            'low_stock' => $stock > 0 && $stock <= $this->low_stock_threshold,
            'sold_count' => (int) $this->sold_count,
            /** Average of approved reviews, 0 when there are none. */
            'rating_avg' => (float) $this->rating_avg,
            'reviews_count' => (int) $this->reviews_count,
            /** @var string|null */
            'image' => ($first = $images->first()) ? Storage::disk('public')->url($first->path) : null,
            /** @var string|null */
            'hover_image' => ($second = $images->get(1)) ? Storage::disk('public')->url($second->path) : null,
            'category' => $this->whenLoaded('category', fn () => [
                'slug' => $this->category->slug,
                'name' => $this->category->localized('name'),
            ]),
        ];
    }
}

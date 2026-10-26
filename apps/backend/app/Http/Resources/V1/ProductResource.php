<?php

namespace App\Http\Resources\V1;

use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Full product for the product page: gallery, variant matrix and option groups.
 *
 * @mixin Product
 */
class ProductResource extends ProductCardResource
{
    public function toArray(Request $request): array
    {
        $variants = $this->variants->where('is_active', true)->values();

        return [
            ...parent::toArray($request),
            'sku' => $this->sku,
            'short_description' => $this->localized('short_description'),
            'description' => $this->localized('description'),
            'stock' => $this->totalStock(),
            'low_stock_threshold' => $this->low_stock_threshold,
            'free_delivery' => $this->free_delivery,
            /** Orders are taken beyond the stock on hand. */
            'preorder' => $this->preorder,
            /** @var string|null */
            'preorder_note' => $this->preorder ? $this->localized('preorder_note') : null,
            /** @var array<int, array{label: string, value: string}> */
            'specifications' => $this->specifications ?? [],
            'brand' => $this->brand ? ['slug' => $this->brand->slug, 'name' => $this->brand->name] : null,
            'images' => $this->images->map(fn ($image) => [
                'url' => Storage::disk('public')->url($image->path),
                'alt' => $image->alt ?: $this->localized('name'),
            ])->values(),
            /** @var array<int, array{id: int, name: string|null, type: string, sort_order: int, values: array<int, array{id: int, label: string|null, color: string|null}>}> */
            'options' => $this->optionGroups($variants),
            'variants' => $variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'label' => $variant->label(),
                'price' => $this->unitPrice($variant),
                /** @var int|null */
                'compare_price' => $this->variantCompare($variant),
                'stock' => $variant->stock,
                /** @var string|null */
                'image' => $variant->image ? Storage::disk('public')->url($variant->image) : null,
                'option_value_ids' => $variant->attributeValues->pluck('id')->values(),
            ]),
            /** Pricier alternatives ("আরও ভালো বিকল্প"). */
            'upsells' => ProductCardResource::collection($this->whenLoaded('upsells', fn () => $this->upsells, collect())),
            /** Buy-together offers that include this product. Prices are for one set. */
            'bundles' => BundleResource::collection($this->whenLoaded('bundles', fn () => $this->bundles, collect())),
            /** Star breakdown of approved reviews, 5 stars down to 1. */
            /** @var array<int, array{stars: int, count: int}> */
            'rating_breakdown' => $this->ratingBreakdown(),
            'meta' => [
                'title' => $this->meta_title ?: $this->localized('name'),
                'description' => $this->meta_description ?: str($this->localized('short_description'))->squish()->limit(160)->toString(),
            ],
        ];
    }

    /** @return list<array{stars: int, count: int}> */
    private function ratingBreakdown(): array
    {
        $counts = $this->reviews_count > 0
            ? $this->reviews()->approved()->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating')
            : collect();

        return collect([5, 4, 3, 2, 1])->map(fn (int $star) => ['stars' => $star, 'count' => (int) ($counts[$star] ?? 0)])->all();
    }

    /** Crossed-out price: the variant's compare price, or during a flash sale its regular price. */
    private function variantCompare(ProductVariant $variant): ?int
    {
        $sale = $this->unitPrice($variant);
        $compare = max((int) $variant->compare_price, $sale < $variant->price ? $variant->price : 0);

        return $compare > $sale ? $compare : null;
    }

    /** Distinct attribute groups used by the variants, e.g. সাইজ: [S, M, L], রং: [সাদা, কালো]. */
    private function optionGroups($variants): array
    {
        return $variants
            ->flatMap(fn (ProductVariant $v) => $v->attributeValues)
            ->unique('id')
            ->groupBy('attribute_id')
            ->map(function ($values) {
                $attribute = $values->first()->attribute;

                return [
                    'id' => $attribute->id,
                    'name' => $attribute->localized('name'),
                    'type' => $attribute->type,
                    'sort_order' => $attribute->sort_order,
                    'values' => $values->sortBy('sort_order')->map(fn (AttributeValue $value) => [
                        'id' => $value->id,
                        'label' => $value->localized('value'),
                        'color' => $value->color_hex,
                    ])->values(),
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->all();
    }
}

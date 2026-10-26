<?php

namespace App\Http\Resources\V1;

use App\Domain\Marketing\Models\LandingPage;
use App\Domain\Marketing\Support\LandingBlocks;
use App\Domain\Marketing\Support\LandingTemplates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin LandingPage
 */
class LandingPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            /** Ready-made design that sets the page's look. */
            /** @var 'classic'|'offer'|'minimal'|'story'|'quick'|'bundle'|'showcase'|'elegant' */
            'template' => LandingTemplates::exists($this->template) ? $this->template : LandingTemplates::DEFAULT,
            /** False keeps the page out of search engines (the default for ad pages). */
            'indexable' => $this->is_indexable && ! $this->isVariant(),
            /** Set on an A/B variant: the main page it is tested against (its canonical URL). */
            /** @var string|null */
            'variant_of' => $this->variant_of_id ? $this->parent?->slug : null,
            /** The product the order form sells; null when it's hidden or deleted. */
            'product' => $product ? new ProductResource($product) : null,
            'blocks' => LandingBlockResource::collection(LandingBlocks::resolve($this->resource)),
            'meta' => [
                'title' => $this->meta_title ?: $this->title,
                'description' => $this->meta_description ?: ($product ? str($product->localized('short_description'))->squish()->limit(160)->toString() : ''),
                /** @var string|null */
                'image' => $this->og_image
                    ? Storage::disk('public')->url($this->og_image)
                    : ($product?->images->first() ? Storage::disk('public')->url($product->images->first()->path) : null),
            ],
        ];
    }
}

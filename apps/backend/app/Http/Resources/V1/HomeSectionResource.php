<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One homepage builder block, resolved. Every type shares this shape; fields a type
 * doesn't use are null or empty.
 *
 * @property array<string, mixed> $resource
 */
class HomeSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $s = $this->resource;

        return [
            'id' => (string) $s['id'],
            /** @var 'hero'|'trust'|'categories'|'products'|'promo_banners'|'help'|'text' */
            'type' => $s['type'],
            /** @var string|null */
            'title' => $s['title'],
            /** @var string|null */
            'note' => $s['note'],
            /** @var string|null */
            'eyebrow' => $s['eyebrow'],
            /** @var string|null */
            'subtitle' => $s['subtitle'],
            /** @var string|null */
            'button_label' => $s['button_label'],
            /** @var string|null */
            'button_url' => $s['button_url'],
            /** "View all" link for product and category rows. */
            /** @var string|null */
            'link_url' => $s['link_url'],
            /** @var 'grid'|'carousel' */
            'layout' => $s['layout'],
            /** @var 4|5 */
            'columns' => $s['columns'],
            /** @var 'plain'|'panel'|'highlight' */
            'style' => $s['style'],
            /** Force this badge on every card in the row. */
            /** @var 'new'|'hot'|'best_seller'|'featured'|'limited'|null */
            'badge' => $s['badge'],
            /** Sanitized HTML (text blocks). */
            /** @var string|null */
            'content' => $s['content'],
            /** @var array<int, array{icon: string, title: string, note: string|null}> */
            'items' => $s['items'],
            /** Flash-sale rows: when the sale ends (ISO 8601), for the countdown. */
            /** @var string|null */
            'ends_at' => $s['ends_at'] ?? null,
            /** Prayer-times block: city key. */
            /** @var string|null */
            'city' => $s['city'] ?? null,
            'products' => ProductCardResource::collection($s['products']),
            'categories' => CategoryResource::collection($s['categories']),
        ];
    }
}

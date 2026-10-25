<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One landing page block, resolved. Every type shares this shape; fields a type doesn't use are
 * null or empty. Item fields: benefits use icon/title/text, reviews title (name)/meta (place)/
 * rating/text/image (screenshot), FAQ title (question)/text (answer).
 *
 * @property array<string, mixed> $resource
 */
class LandingBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $b = $this->resource;

        return [
            'id' => (string) $b['id'],
            /** @var 'hero'|'benefits'|'gallery'|'video'|'text'|'reviews'|'faq'|'cta'|'order_form' */
            'type' => $b['type'],
            /** @var string|null */
            'title' => $b['title'],
            /** @var string|null */
            'subtitle' => $b['subtitle'],
            /** Small label above the hero headline, e.g. "সীমিত অফার". */
            /** @var string|null */
            'badge' => $b['badge'],
            /** @var string|null */
            'button_label' => $b['button_label'],
            /** Sanitized HTML (text blocks). */
            /** @var string|null */
            'content' => $b['content'],
            /** Hero image; null means use the product's own image. */
            /** @var string|null */
            'image' => $b['image'],
            /** @var array<int, string> */
            'images' => $b['images'],
            /** Embeddable player URL (YouTube or Facebook). */
            /** @var string|null */
            'video_url' => $b['video_url'],
            /** Offer countdown end (ISO 8601); null when unset or already past. */
            /** @var string|null */
            'ends_at' => $b['ends_at'],
            /** @var bool */
            'show_price' => $b['show_price'],
            /** @var array<int, array{title: string, text: string|null, meta: string|null, icon: string|null, rating: int|null, image: string|null}> */
            'items' => $b['items'],
        ];
    }
}

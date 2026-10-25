<?php

namespace App\Http\Resources\V1;

use App\Domain\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->localized('name'),
            'description' => $this->localized('description'),
            /** @var string|null */
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'parent_id' => $this->parent_id,
            'is_featured' => $this->is_featured,
            'products_count' => $this->whenCounted('products'),
            'children' => self::collection($this->whenLoaded('children')),
            'meta' => [
                'title' => $this->meta_title,
                'description' => $this->meta_description,
            ],
        ];
    }
}

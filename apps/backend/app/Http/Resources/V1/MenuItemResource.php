<?php

namespace App\Http\Resources\V1;

use App\Domain\Content\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuItem
 */
class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => (string) $this->localized('label'),
            /** Storefront path like /shop?sort=popular, or a full URL. */
            'url' => $this->url,
            'is_highlighted' => $this->is_highlighted,
        ];
    }
}

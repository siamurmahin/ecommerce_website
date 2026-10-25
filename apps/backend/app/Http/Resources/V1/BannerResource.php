<?php

namespace App\Http\Resources\V1;

use App\Domain\Content\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Banner
 */
class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => (string) $this->localized('title'),
            /** @var string|null */
            'subtitle' => $this->localized('subtitle'),
            'image' => Storage::disk('public')->url($this->image),
            /** @var string|null */
            'image_mobile' => $this->image_mobile ? Storage::disk('public')->url($this->image_mobile) : null,
            /** Storefront path like /c/men, or a full URL. */
            /** @var string|null */
            'link' => $this->link,
        ];
    }
}

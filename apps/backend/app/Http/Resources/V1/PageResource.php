<?php

namespace App\Http\Resources\V1;

use App\Domain\Content\Models\Page;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Page
 */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = $this->localized('content');
        $html = $content ? RichContentRenderer::make($content)->toHtml() : '';
        $title = (string) $this->localized('title');

        return [
            'slug' => $this->slug,
            'title' => $title,
            /** Sanitized HTML from the admin rich editor. */
            'content' => $html,
            'updated_at' => $this->updated_at->toIso8601String(),
            'meta' => [
                'title' => $this->meta_title ?: $title,
                'description' => $this->meta_description ?: str(strip_tags($html))->squish()->limit(160)->toString(),
            ],
        ];
    }
}

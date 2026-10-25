<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Models\Page;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PageResource;

class PageController extends Controller
{
    /**
     * Published page slugs, for prerendering and the sitemap.
     *
     * @response array{data: array<int, array{slug: string, updated_at: string}>}
     */
    public function index(): array
    {
        return [
            'data' => Page::active()->orderBy('slug')->get(['slug', 'updated_at'])
                ->map(fn (Page $page) => ['slug' => $page->slug, 'updated_at' => $page->updated_at->toIso8601String()])
                ->all(),
        ];
    }

    public function show(string $slug): PageResource
    {
        return new PageResource(Page::active()->where('slug', $slug)->firstOrFail());
    }
}

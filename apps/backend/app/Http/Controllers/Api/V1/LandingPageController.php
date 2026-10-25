<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\LandingPage;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\LandingPageResource;
use Illuminate\Http\Response;

class LandingPageController extends Controller
{
    /**
     * Published landing page slugs, for prerendering and the sitemap.
     *
     * @response array{data: array<int, array{slug: string, indexable: bool, updated_at: string}>}
     */
    public function index(): array
    {
        return [
            'data' => LandingPage::active()->whereNull('variant_of_id')->orderBy('slug')->get(['slug', 'is_indexable', 'updated_at'])
                ->map(fn (LandingPage $page) => [
                    'slug' => $page->slug,
                    'indexable' => $page->is_indexable,
                    'updated_at' => $page->updated_at->toIso8601String(),
                ])
                ->all(),
        ];
    }

    public function show(string $slug): LandingPageResource
    {
        $page = LandingPage::active()->where('slug', $slug)->firstOrFail();

        // Only a product the shop is actually selling goes on the order form.
        $page->setRelation('product', $page->product_id
            ? Product::visible()->with(['images', 'category', 'brand', 'variants.attributeValues.attribute'])->find($page->product_id)
            : null);

        return new LandingPageResource($page);
    }

    /**
     * Running A/B tests: main page slug → the pages its visitors are split between (main first).
     * Read by the storefront proxy to pick a variant once per visitor.
     *
     * @response array{data: array<string, list<array{slug: string, weight: int}>>}
     */
    public function experiments(): array
    {
        $tests = LandingPage::active()->whereNull('variant_of_id')
            ->whereHas('variants', fn ($q) => $q->active())
            ->with(['variants' => fn ($q) => $q->active()->orderBy('id')])
            ->get()
            ->mapWithKeys(fn (LandingPage $page) => [$page->slug => collect([$page, ...$page->variants])
                ->map(fn (LandingPage $p) => ['slug' => $p->slug, 'weight' => (int) $p->traffic_weight])
                ->filter(fn (array $p) => $p['weight'] > 0)
                ->values()->all()])
            ->filter(fn (array $pages) => $pages !== []);

        return ['data' => (object) $tests->all()];
    }

    /** One page view, counted once per visitor session by the storefront. */
    public function view(string $slug): Response
    {
        LandingPage::active()->where('slug', $slug)->increment('views');

        return response()->noContent();
    }
}

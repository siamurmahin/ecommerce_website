<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\SearchQuery;
use App\Domain\Catalog\Search\Contracts\SearchEngine;
use App\Domain\Catalog\Search\SearchText;
use App\Domain\Marketing\Models\Bundle;
use App\Domain\Marketing\Support\ActiveFlashSales;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductCardResource;
use App\Http\Resources\V1\ProductResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public const CARD_RELATIONS = ['images', 'category', 'variants'];

    /**
     * Filters: category (slug, includes sub-categories), brand (slug), badge,
     * featured=1, q (search: synonyms + spelling fix), sort = relevance (default with q)|newest|price_asc|price_desc|popular.
     */
    public function index(Request $request, SearchEngine $search): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string'],
            'brand' => ['nullable', 'string'],
            'badge' => ['nullable', 'string'],
            'featured' => ['nullable', 'boolean'],
            'flash_sale' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:relevance,newest,price_asc,price_desc,popular'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $query = Product::visible()->with(self::CARD_RELATIONS);

        if ($slug = $validated['category'] ?? null) {
            $category = Category::active()->where('slug', $slug)->firstOrFail();
            $query->whereIn('category_id', $category->descendantIds());
        }

        if ($brand = $validated['brand'] ?? null) {
            $query->whereHas('brand', fn (Builder $q) => $q->where('slug', $brand));
        }

        if ($badge = $validated['badge'] ?? null) {
            $query->where('badge', $badge);
        }

        if ($request->boolean('flash_sale')) {
            $query->whereIn('id', app(ActiveFlashSales::class)->productIds());
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $hits = null;
        if (filled($term = $validated['q'] ?? null)) {
            $hits = $search->search($term);
            $query->whereIn('id', $hits->ids ?: [0]);
        }

        match ($validated['sort'] ?? ($hits ? 'relevance' : 'newest')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'popular' => $query->orderByDesc('sold_count'),
            'relevance' => $this->orderByIds($query, $hits->ids),
            default => $query->latest('published_at')->latest('id'),
        };

        $page = $query->paginate($validated['per_page'] ?? 24)->withQueryString();

        if ($hits && $page->currentPage() === 1) {
            defer(fn () => SearchQuery::record(SearchText::normalize($term), $page->total(), $hits->correctedTo));
        }

        return ProductCardResource::collection($page)->additional([
            /** Set when nothing matched as typed and the results are for a corrected spelling. */
            'search' => ['corrected_to' => $hits?->correctedTo],
        ]);
    }

    /** Keeps the search engine's order (portable CASE, so it works on SQLite and MySQL). */
    private function orderByIds(Builder $query, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $cases = collect(array_slice($ids, 0, 500))->map(fn ($id, $i) => 'WHEN '.(int) $id.' THEN '.$i)->implode(' ');
        $query->orderByRaw("CASE id {$cases} ELSE 999999 END");
    }

    public function show(string $slug): ProductResource
    {
        $product = Product::visible()
            ->where('slug', $slug)
            ->with([
                'images', 'category', 'brand', 'variants.attributeValues.attribute',
                'upsells' => fn ($q) => $q->visible()->with(self::CARD_RELATIONS),
            ])
            ->firstOrFail();

        $product->setRelation('bundles', Bundle::active()
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->with(['items.product' => fn ($q) => $q->visible()->with(self::CARD_RELATIONS)])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Bundle $b) => $b->items->every(fn ($i) => $i->product !== null))
            ->values());

        return new ProductResource($product);
    }

    /**
     * Add-ons for the cart page: cross-sells of the given products, minus what's already in the cart.
     * ids = comma-separated product ids.
     */
    public function crossSells(Request $request): AnonymousResourceCollection
    {
        $ids = collect(explode(',', (string) $request->query('ids')))->map(fn ($id) => (int) $id)->filter()->unique()->take(50)->all();

        $products = Product::visible()
            ->with(self::CARD_RELATIONS)
            ->whereHas('crossSellOf', fn ($q) => $q->whereIn('product_relations.product_id', $ids))
            ->whereNotIn('id', $ids)
            ->orderByDesc('sold_count')
            ->limit(8)
            ->get()
            ->filter(fn (Product $p) => $p->totalStock() > 0)
            ->values();

        return ProductCardResource::collection($products);
    }

    /** Same-category products for "আরো দেখুন". */
    public function related(string $slug): AnonymousResourceCollection
    {
        $product = Product::visible()->where('slug', $slug)->firstOrFail();

        $related = Product::visible()
            ->with(self::CARD_RELATIONS)
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->orderByDesc('sold_count')
            ->limit(8)
            ->get();

        return ProductCardResource::collection($related);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Search\Contracts\SearchEngine;
use App\Domain\Catalog\Search\SearchHits;
use App\Domain\Catalog\Search\SearchText;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductCardResource;
use App\Settings\SearchSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Search-as-you-type: a few products and categories for the dropdown, plus the admin's
     * popular searches (shown before the shopper types).
     */
    public function suggest(Request $request, SearchEngine $search, SearchText $text, SearchSettings $settings): JsonResponse
    {
        $term = (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');
        $typed = mb_strlen(SearchText::normalize($term)) >= 2;
        $hits = $typed ? $search->search($term, 6) : new SearchHits([]);

        $products = Product::visible()->with(ProductController::CARD_RELATIONS)->whereIn('id', $hits->ids ?: [0])->get()
            ->sortBy(fn (Product $p) => array_search($p->id, $hits->ids, true))->values();

        $categories = ! $typed ? collect() : Category::active()
            // Any spelling of any word (synonyms included), so "ator" also finds "আতর ও সুগন্ধি".
            ->where(function ($q) use ($text, $hits, $term) {
                foreach (collect($text->words($hits->correctedTo ?? $term))->flatten()->unique() as $word) {
                    $needle = '%'.addcslashes($word, '%_\\').'%';
                    $q->orWhere('name', 'like', $needle)->orWhere('name_en', 'like', $needle);
                }
            })
            ->limit(3)->get();

        return response()->json([
            'products' => ProductCardResource::collection($products),
            /** @var list<array{name: string|null, slug: string}> */
            'categories' => $categories->map(fn (Category $c) => ['name' => $c->localized('name'), 'slug' => $c->slug])->values()->all(),
            /** Set when the products are for a corrected spelling. */
            /** @var string|null */
            'corrected_to' => $hits->correctedTo,
            /** @var list<string> */
            'popular' => array_values(array_filter(array_map('strval', $settings->popular))),
        ]);
    }
}

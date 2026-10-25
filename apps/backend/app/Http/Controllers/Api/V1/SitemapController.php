<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\Page;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class SitemapController extends Controller
{
    /**
     * Every public URL the storefront should list in sitemap.xml, with last-modified dates.
     *
     * @response array{
     *   products: array<int, array{slug: string, updated_at: string, images: array<int, string>}>,
     *   categories: array<int, array{slug: string, updated_at: string}>,
     *   pages: array<int, array{slug: string, updated_at: string}>
     * }
     */
    public function __invoke(): array
    {
        $products = Product::visible()
            ->with('images:id,product_id,path,sort_order')
            ->latest('updated_at')
            ->get(['id', 'slug', 'updated_at'])
            ->map(fn (Product $product) => [
                'slug' => $product->slug,
                'updated_at' => $product->updated_at->toIso8601String(),
                'images' => $product->images->take(5)->map(fn ($image) => Storage::disk('public')->url($image->path))->values()->all(),
            ]);

        $simple = fn ($model) => ['slug' => $model->slug, 'updated_at' => $model->updated_at->toIso8601String()];

        return [
            'products' => $products->all(),
            'categories' => Category::active()->orderBy('sort_order')->get(['slug', 'updated_at'])->map($simple)->all(),
            'pages' => Page::active()->orderBy('slug')->get(['slug', 'updated_at'])->map($simple)->all(),
        ];
    }
}

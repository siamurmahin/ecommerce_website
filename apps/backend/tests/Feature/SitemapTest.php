<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_only_public_urls(): void
    {
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        Category::create(['name' => 'লুকানো', 'slug' => 'hidden', 'is_active' => false]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 5, 'is_active' => true,
        ]);
        $product->images()->create(['path' => 'products/oud.jpg', 'sort_order' => 0]);
        Product::create([
            'category_id' => $category->id, 'name' => 'ড্রাফট', 'slug' => 'draft',
            'type' => ProductType::Simple, 'price' => 100, 'stock' => 1, 'is_active' => false,
        ]);
        Product::create([
            'category_id' => $category->id, 'name' => 'আসছে', 'slug' => 'coming-soon', 'published_at' => now()->addDay(),
            'type' => ProductType::Simple, 'price' => 100, 'stock' => 1, 'is_active' => true,
        ]);
        Page::create(['slug' => 'about', 'title' => 'আমাদের সম্পর্কে', 'content' => '<p>…</p>', 'is_active' => true]);
        Page::create(['slug' => 'draft-page', 'title' => 'ড্রাফট', 'content' => '<p>…</p>', 'is_active' => false]);

        $this->getJson('/api/v1/sitemap')
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.slug', 'oud-attar')
            ->assertJsonPath('products.0.images.0', fn (string $url) => str_ends_with($url, 'products/oud.jpg'))
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.slug', 'attar')
            ->assertJsonCount(1, 'pages')
            ->assertJsonPath('pages.0.slug', 'about');
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\SearchQuery;
use App\Domain\Catalog\Search\Drivers\DatabaseSearch;
use App\Filament\Pages\ManageSearchSettings;
use App\Filament\Resources\SearchQueries\SearchQueryResource;
use App\Models\User;
use App\Settings\SearchSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $attar = Category::create(['name' => 'আতর ও সুগন্ধি', 'name_en' => 'Attar', 'slug' => 'attar']);
        $mats = Category::create(['name' => 'ইবাদত সামগ্রী', 'name_en' => 'Prayer essentials', 'slug' => 'prayer']);

        $make = fn (Category $c, string $name, ?string $en, string $slug, int $sold = 0) => Product::create([
            'category_id' => $c->id, 'name' => $name, 'name_en' => $en, 'slug' => $slug,
            'type' => ProductType::Simple, 'price' => 50000, 'stock' => 5, 'is_active' => true, 'sold_count' => $sold,
        ]);
        $make($attar, 'উদ আতর', 'Oud Attar', 'oud-attar', 10);
        $make($attar, 'হোয়াইট মাস্ক', 'White Musk', 'white-musk', 50);
        $make($mats, 'তুর্কি ভেলভেট জায়নামাজ', 'Turkish Velvet Prayer Mat', 'velvet-mat');
        $make($mats, 'ক্রিস্টাল তাসবিহ', 'Crystal Tasbih', 'crystal-tasbih');
    }

    private function slugs(string $q): array
    {
        return collect($this->getJson('/api/v1/products?q='.urlencode($q))->assertOk()->json('data'))->pluck('slug')->all();
    }

    public function test_synonyms_and_category_names_match(): void
    {
        // "ator" is in the default synonyms for আতর; the musk matches through its category name.
        $this->assertSame(['oud-attar', 'white-musk'], $this->slugs('ator'));
        $this->assertSame(['velvet-mat'], $this->slugs('prayer mat'));
        $this->assertSame(['velvet-mat'], $this->slugs('জায়নামাজ'));
    }

    public function test_name_matches_rank_above_category_matches(): void
    {
        // Musk sells more, but the oud has "আতর" in its own name.
        $this->assertSame(['oud-attar', 'white-musk'], $this->slugs('আতর'));
    }

    public function test_typos_are_corrected_and_reported(): void
    {
        $this->getJson('/api/v1/products?q=tasbeh')->assertOk()
            ->assertJsonPath('data.0.slug', 'crystal-tasbih')
            ->assertJsonPath('search.corrected_to', 'tasbih');

        $this->getJson('/api/v1/products?q=velvat')->assertJsonPath('data.0.slug', 'velvet-mat');
        $this->assertSame([], $this->slugs('xyzzyq'));
    }

    public function test_bangla_distance_counts_characters_not_bytes(): void
    {
        $this->assertSame(1, DatabaseSearch::distance('আতর', 'আতার'));
        $this->assertSame(0, DatabaseSearch::distance('জায়নামাজ', 'জায়নামাজ'));
    }

    public function test_searches_are_logged_with_their_result_count(): void
    {
        $this->withoutDefer();
        $this->getJson('/api/v1/products?q=Oud')->assertOk();
        $this->getJson('/api/v1/products?q=oud')->assertOk();
        $this->getJson('/api/v1/products?q='.urlencode('গোলাপজল'))->assertOk();

        $this->assertSame(2, SearchQuery::where('term', 'oud')->value('searches'));
        $this->assertSame(0, SearchQuery::where('term', 'গোলাপজল')->value('results'));
    }

    public function test_suggest_returns_products_categories_and_popular_terms(): void
    {
        $this->getJson('/api/v1/search/suggest')->assertOk()
            ->assertJsonCount(0, 'products')
            ->assertJsonPath('popular.0', 'আতর');

        $this->getJson('/api/v1/search/suggest?q=attar&locale=en')->assertOk()
            ->assertJsonPath('products.0.slug', 'oud-attar')
            ->assertJsonPath('categories.0.slug', 'attar');

        $this->getJson('/api/v1/search/suggest?q=ator')->assertJsonPath('categories.0.slug', 'attar');
    }

    public function test_admin_edits_synonyms(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('catalog_manager'));

        Livewire::test(ManageSearchSettings::class)
            ->fillForm(['synonyms' => "তাসবিহ, misbaha\n\n", 'popular' => ['তাসবিহ']])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['তাসবিহ, misbaha'], app(SearchSettings::class)->refresh()->synonyms);
        $this->assertSame(['crystal-tasbih'], $this->slugs('misbaha'));
        $this->get(SearchQueryResource::getUrl())->assertOk();

        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));
        $this->get(ManageSearchSettings::getUrl())->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\Page;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorefrontRevalidateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['shop.storefront_url' => 'http://store.test', 'shop.revalidate_secret' => 'secret']);
        Http::fake(['store.test/*' => Http::response(['ok' => true])]);
        $this->withoutDefer();
    }

    private function assertRevalidated(array $tags): void
    {
        Http::assertSent(fn (Request $request) => $request->url() === 'http://store.test/api/revalidate'
            && $request->header('X-Revalidate-Secret') === ['secret']
            && array_diff($tags, $request['tags']) === []);
    }

    public function test_saving_settings_refreshes_the_storefront_shell(): void
    {
        $settings = app(SiteSettings::class);
        $settings->hotline = '09610-000000';
        $settings->save();

        $this->assertRevalidated(['settings']);
    }

    public function test_renaming_a_page_drops_both_urls(): void
    {
        $page = Page::create(['slug' => 'about', 'title' => 'আমাদের কথা']);
        $page->update(['slug' => 'about-us']);

        $this->assertRevalidated(['pages', 'page:about-us', 'page:about']);
    }

    public function test_catalog_changes_refresh_the_catalog(): void
    {
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        Product::create(['category_id' => $category->id, 'name' => 'উদ', 'slug' => 'oud', 'type' => ProductType::Simple, 'price' => 100, 'stock' => 1]);

        $this->assertRevalidated(['catalog']);
    }

    public function test_nothing_is_sent_without_a_secret(): void
    {
        config(['shop.revalidate_secret' => null]);
        Page::create(['slug' => 'faq', 'title' => 'FAQ']);

        Http::assertNothingSent();
    }

    public function test_a_down_storefront_never_breaks_the_save(): void
    {
        Http::fake(['store.test/*' => Http::response(null, 500)]);

        Page::create(['slug' => 'terms', 'title' => 'শর্তাবলি']);

        $this->assertDatabaseHas('pages', ['slug' => 'terms']);
    }
}

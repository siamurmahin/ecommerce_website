<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Filament\Pages\ProductFeeds;
use App\Models\User;
use App\Settings\FeedSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductFeedTest extends TestCase
{
    use RefreshDatabase;

    private Category $attar;

    private Category $panjabi;

    protected function setUp(): void
    {
        parent::setUp();
        config(['shop.storefront_url' => 'https://looksjhorna.test']);

        $this->attar = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->panjabi = Category::create(['name' => 'পাঞ্জাবি', 'slug' => 'panjabi']);

        Product::create([
            'category_id' => $this->attar->id, 'name' => 'উদ আতর', 'name_en' => 'Oud Attar', 'slug' => 'oud-attar', 'sku' => 'AT-1',
            'type' => ProductType::Simple, 'price' => 45000, 'compare_price' => 50000, 'stock' => 5, 'is_active' => true,
            'description' => '<p>খাঁটি <b>উদ</b></p>',
        ]);
        Product::create([
            'category_id' => $this->attar->id, 'name' => 'গোলাপ আতর', 'slug' => 'rose-attar',
            'type' => ProductType::Simple, 'price' => 30000, 'stock' => 0, 'is_active' => true,
        ]);
        Product::create([
            'category_id' => $this->attar->id, 'name' => 'লুকানো', 'slug' => 'hidden', 'type' => ProductType::Simple, 'price' => 100, 'stock' => 5, 'is_active' => false,
        ]);

        $panjabi = Product::create([
            'category_id' => $this->panjabi->id, 'name' => 'পাঞ্জাবি', 'slug' => 'panjabi', 'sku' => 'PJ',
            'type' => ProductType::Variable, 'price' => 150000, 'stock' => 0, 'is_active' => true,
        ]);
        $size = Attribute::create(['name' => 'সাইজ', 'slug' => 'size']);
        foreach (['M' => 3, 'L' => 0] as $value => $stock) {
            $variant = $panjabi->variants()->create(['sku' => "PJ-{$value}", 'price' => 160000, 'stock' => $stock, 'is_active' => true]);
            $variant->attributeValues()->attach($size->values()->create(['value' => $value])->id);
        }
    }

    private function enable(array $overrides = []): void
    {
        app(FeedSettings::class)->fill(['enabled' => true, ...$overrides])->save();
    }

    /** @return Collection<string, \SimpleXMLElement> g: fields keyed by item id */
    private function items(string $url): Collection
    {
        $items = collect();
        foreach (simplexml_load_string($this->get($url)->assertOk()->getContent())->channel->item as $item) {
            $g = $item->children('g', true);
            $items[(string) $g->id] = $g;
        }

        return $items;
    }

    public function test_feed_is_off_until_enabled(): void
    {
        $this->get('/feeds/facebook.xml')->assertNotFound();
        $this->enable();
        $this->get('/feeds/facebook.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->get('/feeds/tiktok.xml')->assertNotFound();
    }

    public function test_items_cover_simple_products_and_each_variant(): void
    {
        $this->enable();
        $items = $this->items('/feeds/google.xml');

        $this->assertSame(['AT-1', 'P2', 'PJ-M', 'PJ-L'], $items->keys()->all());

        $oud = $items['AT-1'];
        $this->assertSame('500.00 BDT', (string) $oud->price);
        $this->assertSame('450.00 BDT', (string) $oud->sale_price);
        $this->assertSame('খাঁটি উদ', (string) $oud->description);
        $this->assertSame('https://looksjhorna.test/p/oud-attar', (string) $oud->link);
        $this->assertSame('in_stock', (string) $oud->availability);
        $this->assertSame('out_of_stock', (string) $items['P2']->availability);

        $this->assertSame('PJ', (string) $items['PJ-M']->item_group_id);
        $this->assertSame('পাঞ্জাবি — M', (string) $items['PJ-M']->title);
        $this->assertSame('out_of_stock', (string) $items['PJ-L']->availability);
    }

    public function test_settings_filter_categories_stock_and_language(): void
    {
        $this->enable(['include_out_of_stock' => false, 'category_ids' => [$this->attar->id], 'locale' => 'en']);
        $items = $this->items('/feeds/facebook.xml');

        $this->assertSame(['AT-1'], $items->keys()->all());
        $this->assertSame('Oud Attar', (string) $items['AT-1']->title);
        $this->assertSame('https://looksjhorna.test/en/p/oud-attar', (string) $items['AT-1']->link);
    }

    public function test_admin_page_saves_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin);

        Livewire::test(ProductFeeds::class)
            ->assertSee('/feeds/google.xml')
            ->set('data.enabled', true)
            ->set('data.category_ids', [(string) $this->panjabi->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$this->panjabi->id], app(FeedSettings::class)->refresh()->category_ids);
        $this->assertTrue(app(FeedSettings::class)->enabled);
    }
}

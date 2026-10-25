<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Filament\Pages\ManageBrandSettings;
use App\Filament\Pages\ManageHomepage;
use App\Filament\Pages\Rewards;
use App\Models\User;
use App\Settings\BrandSettings;
use App\Settings\HomeSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrandAndHomepageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
    }

    private function product(string $slug, array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'attar'], ['name' => 'আতর']);

        return Product::create([
            'category_id' => $category->id, 'name' => $slug, 'slug' => $slug,
            'type' => ProductType::Simple, 'price' => 1000, 'stock' => 5, 'is_active' => true, ...$attributes,
        ]);
    }

    public function test_settings_api_exposes_brand_and_theme(): void
    {
        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('brand.name', 'Looks Jhorna')
            ->assertJsonPath('brand.logo', null)
            ->assertJsonPath('theme.colors.primary', '#0F4C3A')
            ->assertJsonPath('theme.radius', 14)
            ->assertJsonPath('theme.fonts.heading_bn', 'noto-serif-bengali');
    }

    public function test_admin_changes_brand_and_bad_colours_are_rejected(): void
    {
        $this->admin();

        Livewire::test(ManageBrandSettings::class)
            ->set('data.name', 'Noor Shop')
            ->set('data.color_primary', '#7a1f3d')
            ->set('data.font_heading', 'playfair')
            ->call('save')
            ->assertHasNoErrors();

        $brand = app(BrandSettings::class)->refresh();
        $this->assertSame('Noor Shop', $brand->name);
        $this->assertSame('#7A1F3D', $brand->color_primary);

        Livewire::test(ManageBrandSettings::class)
            ->set('data.color_text', 'red; } body { display:none')
            ->set('data.font_body', 'comic-sans')
            ->call('save')
            ->assertHasErrors(['data.color_text', 'data.font_body']);
    }

    public function test_home_sections_follow_builder_order_and_hidden_blocks_disappear(): void
    {
        $this->product('featured-one', ['is_featured' => true]);
        $this->product('plain-one');

        $settings = app(HomeSettings::class);
        $settings->sections = [
            ['type' => 'text', 'data' => ['enabled' => true, 'title' => 'স্বাগতম', 'content' => '<p>হ্যালো</p><script>alert(1)</script>']],
            ['type' => 'products', 'data' => ['enabled' => true, 'title' => 'নতুন', 'source' => 'new', 'limit' => 8]],
            ['type' => 'help', 'data' => ['enabled' => false, 'title' => 'লুকানো']],
            ['type' => 'products', 'data' => ['enabled' => true, 'title' => 'খালি', 'source' => 'deals', 'limit' => 4]],
            ['type' => 'unknown', 'data' => ['enabled' => true]],
        ];
        $settings->save();

        $response = $this->getJson('/api/v1/home?locale=bn')->assertOk()
            ->assertJsonCount(2, 'sections') // hidden, empty (no deals) and unknown blocks are dropped
            ->assertJsonPath('sections.0.type', 'text')
            ->assertJsonPath('sections.1.title', 'নতুন')
            ->assertJsonCount(2, 'sections.1.products');

        $this->assertStringNotContainsString('<script', $response->json('sections.0.content'));
    }

    public function test_product_rows_never_repeat_and_category_source_includes_children(): void
    {
        $parent = Category::create(['name' => 'পোশাক', 'slug' => 'clothing']);
        $child = Category::create(['name' => 'পাঞ্জাবি', 'slug' => 'panjabi', 'parent_id' => $parent->id]);
        $this->product('panjabi-one', ['category_id' => $child->id, 'is_featured' => true]);
        $this->product('panjabi-two', ['category_id' => $child->id]);
        $this->product('attar-one');

        $settings = app(HomeSettings::class);
        $settings->sections = [
            ['type' => 'products', 'data' => ['title' => 'Featured', 'source' => 'featured', 'limit' => 4]],
            ['type' => 'products', 'data' => ['title' => 'Clothing', 'source' => 'category', 'category_id' => $parent->id, 'limit' => 4]],
        ];
        $settings->save();

        $this->getJson('/api/v1/home')
            ->assertJsonPath('sections.0.products.0.slug', 'panjabi-one')
            ->assertJsonCount(1, 'sections.1.products')
            ->assertJsonPath('sections.1.products.0.slug', 'panjabi-two');
    }

    public function test_admin_saves_homepage_layout(): void
    {
        $this->admin();
        $default = app(HomeSettings::class)->sections;

        Livewire::test(ManageHomepage::class)
            ->set('data.sections', array_reverse($default))
            ->call('save')
            ->assertHasNoErrors();

        $saved = app(HomeSettings::class)->refresh()->sections;
        $this->assertSame('help', $saved[0]['type']);
        $this->assertSame('hero', end($saved)['type']);
        $this->assertTrue(array_is_list($saved));
    }

    public function test_marketing_and_builder_pages_need_permission(): void
    {
        $this->admin();
        $this->get(Rewards::getUrl())->assertOk()->assertSee('রেফারেল চালু');
        $this->get(ManageHomepage::getUrl())->assertOk();

        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));
        $this->get(Rewards::getUrl())->assertForbidden();
        $this->get(ManageBrandSettings::getUrl())->assertForbidden();
    }
}

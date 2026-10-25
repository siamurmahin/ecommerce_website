<?php

namespace Tests\Feature;

use App\Domain\Ai\AiUnavailable;
use App\Domain\Ai\Contracts\Copywriter;
use App\Domain\Ai\Drivers\ClaudeCopywriter;
use App\Domain\Ai\ProductCopy;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Filament\Pages\ManageAiSettings;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\User;
use App\Settings\AiSettings;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiCopyTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        $settings = app(AiSettings::class);
        $settings->enabled = true;
        $settings->api_key = 'sk-ant-test';
        $settings->save();
    }

    public function test_copy_is_sanitized_and_trimmed(): void
    {
        $copy = ProductCopy::fromArray([
            'short_description' => "নরম সুতি\nআরামদায়ক",
            'description_html' => '<p>ভালো <strong>পাঞ্জাবি</strong></p><script>alert(1)</script><img src=x onerror=1>',
            'short_description_en' => 'Soft cotton',
            'description_en_html' => '<p>Nice</p>',
            'meta_title' => str_repeat('ক', 90),
            'meta_description' => 'বিবরণ',
        ]);

        $this->assertSame('<p>ভালো <strong>পাঞ্জাবি</strong></p>alert(1)', $copy->description);
        $this->assertSame(70, mb_strlen($copy->metaTitle));
    }

    public function test_driver_refuses_without_a_key(): void
    {
        $this->expectException(AiUnavailable::class);
        app(ClaudeCopywriter::class)->productCopy(['name' => 'x', 'name_en' => null, 'category' => null, 'price' => null, 'specifications' => [], 'notes' => null, 'current' => null]);
    }

    public function test_product_form_fills_in_ai_copy(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('catalog_manager'));
        $this->enable();

        $category = Category::create(['name' => 'পাঞ্জাবি', 'slug' => 'panjabi']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'সুতি পাঞ্জাবি', 'slug' => 'cotton-panjabi', 'type' => ProductType::Simple, 'price' => 245000, 'stock' => 3, 'is_active' => true]);

        $fake = new class implements Copywriter
        {
            public array $facts = [];

            public function productCopy(array $facts): ProductCopy
            {
                $this->facts = $facts;

                return ProductCopy::fromArray([
                    'short_description' => 'নরম সুতি', 'description_html' => '<p>আরামদায়ক পাঞ্জাবি</p>',
                    'short_description_en' => 'Soft cotton', 'description_en_html' => '<p>Comfortable</p>',
                    'meta_title' => 'সুতি পাঞ্জাবি', 'meta_description' => 'অর্ডার করুন',
                ]);
            }
        };
        $this->app->instance(Copywriter::class, $fake);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(TestAction::make('writeWithAi')->schemaComponent('content'), ['notes' => 'ঈদের জন্য', 'parts' => ['description', 'seo']])
            ->assertHasNoActionErrors()
            ->assertSchemaStateSet(['meta_title' => 'সুতি পাঞ্জাবি', 'meta_description' => 'অর্ডার করুন', 'short_description' => null])
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertStringContainsString('Comfortable', $product->description_en);
        $this->assertSame('সুতি পাঞ্জাবি', $product->meta_title);

        $this->assertSame(245000, $fake->facts['price']);
        $this->assertSame('পাঞ্জাবি', $fake->facts['category']);
        $this->assertSame('ঈদের জন্য', $fake->facts['notes']);
    }

    public function test_ai_settings_keep_the_key_and_are_admin_only(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
        $this->enable();

        Livewire::test(ManageAiSettings::class)
            ->set('data.model', 'claude-sonnet-5-5')
            ->set('data.api_key', '')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(AiSettings::class)->refresh();
        $this->assertSame('sk-ant-test', $settings->api_key);
        $this->assertSame('claude-sonnet-5-5', $settings->model);

        $this->actingAs(tap(User::factory()->create())->assignRole('catalog_manager'));
        $this->get(ManageAiSettings::getUrl())->assertForbidden();
    }
}

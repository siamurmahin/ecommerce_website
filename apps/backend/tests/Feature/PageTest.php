<?php

namespace Tests\Feature;

use App\Domain\Content\Models\Page;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_is_served_localized_and_sanitized(): void
    {
        Page::create([
            'slug' => 'about', 'title' => 'আমাদের সম্পর্কে', 'title_en' => 'About us',
            'content' => '<p>বাংলা</p><script>alert(1)</script>', 'content_en' => '<p>English</p>',
        ]);

        $this->getJson('/api/v1/pages/about?locale=bn')
            ->assertOk()
            ->assertJsonPath('data.title', 'আমাদের সম্পর্কে')
            ->assertJsonPath('data.content', '<p>বাংলা</p>')
            ->assertJsonPath('data.meta.description', 'বাংলা');

        $this->getJson('/api/v1/pages/about?locale=en')->assertJsonPath('data.content', '<p>English</p>');
    }

    public function test_unpublished_and_missing_pages_404(): void
    {
        Page::create(['slug' => 'draft', 'title' => 'খসড়া', 'is_active' => false]);

        $this->getJson('/api/v1/pages/draft')->assertNotFound();
        $this->getJson('/api/v1/pages/nope')->assertNotFound();
        $this->getJson('/api/v1/pages')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_seeder_keeps_admin_edits(): void
    {
        Page::create(['slug' => 'faq', 'title' => 'আমার FAQ']);
        $this->seed(PageSeeder::class);

        $this->assertSame('আমার FAQ', Page::where('slug', 'faq')->value('title'));
        $this->assertSame(7, Page::count());
    }

    public function test_page_admin_requires_permission(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
        $this->get('/admin/pages')->assertOk();
        $this->get('/admin/pages/create')->assertOk();

        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));
        $this->get('/admin/pages')->assertForbidden();
    }
}

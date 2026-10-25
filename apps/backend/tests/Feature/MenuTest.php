<?php

namespace Tests\Feature;

use App\Domain\Content\Models\MenuItem;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menus_return_active_items_in_order(): void
    {
        $this->seed(MenuSeeder::class);
        MenuItem::where('url', '/shop')->update(['is_active' => false]);
        MenuItem::where('url', '/')->update(['sort_order' => 99]);

        $this->getJson('/api/v1/menus?locale=en')
            ->assertOk()
            ->assertJsonCount(4, 'header')
            ->assertJsonPath('header.0.label', 'Best Selling')
            ->assertJsonPath('header.2.is_highlighted', true)
            ->assertJsonPath('header.3.url', '/')
            ->assertJsonCount(4, 'footer_help')
            ->assertJsonPath('footer_company.0.url', '/about');
    }

    public function test_seeder_does_not_overwrite_an_edited_menu(): void
    {
        MenuItem::create(['label' => 'শুধু এটা', 'url' => '/shop']);
        $this->seed(MenuSeeder::class);

        $this->assertSame(1, MenuItem::where('location', 'header')->count());
        $this->assertTrue(MenuItem::where('location', 'footer_help')->exists());
    }

    public function test_menu_admin_requires_permission(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
        $this->get('/admin/menu-items')->assertOk();

        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));
        $this->get('/admin/menu-items')->assertForbidden();
    }
}

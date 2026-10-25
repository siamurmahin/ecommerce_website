<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'super_admin'): User
    {
        $this->seed(RoleSeeder::class);

        return tap(User::factory()->create())->assignRole($role);
    }

    public function test_super_admin_can_open_catalog_screens(): void
    {
        $this->actingAs($this->admin());

        foreach (['products', 'products/create', 'categories', 'categories/create', 'brands', 'attributes', 'attributes/create'] as $path) {
            $this->get("/admin/{$path}")->assertOk();
        }
    }

    public function test_staff_without_permission_cannot_open_products(): void
    {
        $this->actingAs($this->admin('content_editor'));

        $this->get('/admin/products')->assertForbidden();
    }

    public function test_user_without_role_cannot_enter_panel(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')->assertForbidden();
    }
}

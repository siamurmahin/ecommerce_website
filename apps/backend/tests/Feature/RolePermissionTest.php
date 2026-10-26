<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Generate every resource/page permission first, as on a real install.
        Artisan::call('shield:generate', ['--all' => true, '--panel' => 'admin', '--option' => 'permissions', '--no-interaction' => true]);
        $this->seed(RoleSeeder::class);
    }

    private function as(string $role): self
    {
        return $this->actingAs(tap(User::factory()->create())->assignRole($role));
    }

    public function test_each_role_reaches_its_own_area_only(): void
    {
        $matrix = [
            'order_manager' => ['allowed' => ['/admin/orders', '/admin/sms-logs', '/admin/incomplete-orders'], 'denied' => ['/admin/settings/courier', '/admin/banners', '/admin/settings/brand', '/admin/users']],
            'warehouse' => ['allowed' => ['/admin/orders', '/admin/products'], 'denied' => ['/admin/categories', '/admin/homepage']],
            'catalog_manager' => ['allowed' => ['/admin/products', '/admin/categories', '/admin/brands'], 'denied' => ['/admin/orders', '/admin/settings/tracking']],
            'marketer' => ['allowed' => ['/admin/banners', '/admin/homepage', '/admin/settings/tracking', '/admin/coupons', '/admin/flash-sales'], 'denied' => ['/admin/users', '/admin/expenses', '/admin/settings/sms']],
            'content_editor' => ['allowed' => ['/admin/pages', '/admin/menu-items', '/admin/homepage'], 'denied' => ['/admin/orders', '/admin/settings/brand']],
            'manager' => ['allowed' => ['/admin/orders', '/admin/expenses', '/admin/settings/brand', '/admin/settings/sms', '/admin/homepage'], 'denied' => ['/admin/users', '/admin/shield/roles']],
        ];

        foreach ($matrix as $role => $paths) {
            $this->as($role);
            foreach ($paths['allowed'] as $path) {
                $this->assertSame(200, $this->get($path)->status(), "{$role} → {$path}");
            }
            foreach ($paths['denied'] as $path) {
                $this->assertSame(403, $this->get($path)->status(), "{$role} ✗ {$path}");
            }
        }
    }

    public function test_super_admin_manages_staff(): void
    {
        $this->as('super_admin')->get('/admin/users')->assertOk()->assertSee('স্টাফ');
    }

    public function test_reseeding_keeps_permissions_granted_later(): void
    {
        $role = Role::findByName('warehouse');
        $role->givePermissionTo('ViewAny:Banner');

        $this->seed(RoleSeeder::class);

        $this->assertTrue($role->fresh()->hasPermissionTo('ViewAny:Banner'));
    }
}

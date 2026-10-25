<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Production mode: the set-up route is registered at boot, so the flag is set before the app starts. */
class AdminTwoFactorRequiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $_ENV['ADMIN_REQUIRE_2FA'] = $_SERVER['ADMIN_REQUIRE_2FA'] = 'true';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset($_ENV['ADMIN_REQUIRE_2FA'], $_SERVER['ADMIN_REQUIRE_2FA']);
        parent::tearDown();
    }

    public function test_staff_without_an_authenticator_must_set_one_up(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));
        $setUp = Filament::getPanel('admin')->route('auth.multi-factor-authentication.set-up-required');
        $this->get('/admin')->assertRedirect($setUp);
        $this->get($setUp)->assertOk();

        $ready = tap(User::factory()->create())->assignRole('order_manager');
        $ready->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $this->actingAs($ready);
        $this->get('/admin')->assertOk();
    }
}

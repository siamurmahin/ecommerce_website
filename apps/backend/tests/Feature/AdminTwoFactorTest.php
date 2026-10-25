<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, bool $withTwoFactor = false): User
    {
        $user = tap(User::factory()->create())->assignRole($role);
        if ($withTwoFactor) {
            $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
            $user->saveAppAuthenticationRecoveryCodes(['aaaa-bbbb']);
        }

        return $user;
    }

    public function test_secret_and_recovery_codes_are_encrypted_and_hidden(): void
    {
        $this->seed(RoleSeeder::class);
        $user = $this->staff('order_manager', withTwoFactor: true);

        $raw = $user->getConnection()->table('users')->where('id', $user->id)->value('app_authentication_secret');
        $this->assertNotSame('JBSWY3DPEHPK3PXP', $raw);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->getAppAuthenticationSecret());
        $this->assertArrayNotHasKey('app_authentication_secret', $user->toArray());
    }

    public function test_super_admin_can_reset_a_lost_authenticator(): void
    {
        $this->seed(RoleSeeder::class);
        $staff = $this->staff('order_manager', withTwoFactor: true);
        $this->actingAs($this->staff('super_admin'));

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('resetTwoFactor')->table($staff))
            ->assertHasNoActionErrors();

        $this->assertNull($staff->fresh()->getAppAuthenticationSecret());
        $this->assertNull($staff->fresh()->getAppAuthenticationRecoveryCodes());
    }
}

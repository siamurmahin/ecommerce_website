<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSiteSettings;
use App\Models\User;
use App\Settings\SiteSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_api_exposes_contact_copy_and_only_filled_socials(): void
    {
        $settings = app(SiteSettings::class);
        $settings->hotline = '09610-123456';
        $settings->whatsapp = '01812345678';
        $settings->facebook_url = 'https://facebook.com/looksjhorna';
        $settings->announcement_en = null;
        $settings->save();

        $this->getJson('/api/v1/settings?locale=en')
            ->assertOk()
            ->assertJsonPath('hotline', '09610-123456')
            ->assertJsonPath('hotline_tel', '09610123456')
            ->assertJsonPath('whatsapp_url', 'https://wa.me/8801812345678')
            ->assertJsonPath('announcement', 'সারা বাংলাদেশে ক্যাশ অন ডেলিভারি') // English empty → Bangla
            ->assertJsonCount(1, 'socials')
            ->assertJsonPath('socials.0.network', 'facebook');
    }

    public function test_admin_can_save_settings_and_whatsapp_is_normalized(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));

        Livewire::test(ManageSiteSettings::class)
            ->set('data.hotline', '01999-000000')
            ->set('data.whatsapp', '+880 1712-345678')
            ->set('data.announcement', 'ঈদ অফার চলছে')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(SiteSettings::class)->refresh();
        $this->assertSame('01999-000000', $settings->hotline);
        $this->assertSame('01712345678', $settings->whatsapp);
        $this->assertSame('ঈদ অফার চলছে', $settings->announcement);

        Livewire::test(ManageSiteSettings::class)
            ->set('data.whatsapp', '12345')
            ->call('save')
            ->assertHasErrors(['data.whatsapp']);
    }

    public function test_settings_page_requires_permission(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
        $this->get('/admin/settings/site')->assertOk();

        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));
        $this->get('/admin/settings/site')->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSeasonalSettings;
use App\Models\User;
use App\Settings\HomeSettings;
use App\Settings\SeasonalSettings;
use App\Settings\SiteSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeasonalTest extends TestCase
{
    use RefreshDatabase;

    private function ramadan(string $from, string $to): void
    {
        $settings = app(SeasonalSettings::class);
        $settings->ramadan_enabled = true;
        $settings->ramadan_starts_on = $from;
        $settings->ramadan_ends_on = $to;
        $settings->ramadan_announcement = 'রমজান অফার';
        $settings->save();
    }

    public function test_ramadan_mode_swaps_the_announcement_only_between_its_dates(): void
    {
        $site = app(SiteSettings::class);
        $site->announcement = 'সাধারণ ঘোষণা';
        $site->save();

        $this->travelTo(now('Asia/Dhaka')->setDate(2027, 2, 8)->setTime(0, 30)); // still Feb 7 in UTC
        $this->ramadan('2027-02-08', '2027-03-09');

        $this->getJson('/api/v1/settings?locale=bn')->assertOk()
            ->assertJsonPath('announcement', 'রমজান অফার')
            ->assertJsonPath('ramadan.active', true)
            ->assertJsonPath('ramadan.starts_on', '2027-02-08')
            ->assertJsonPath('prayer.city', 'dhaka');

        $this->travelTo(now('Asia/Dhaka')->setDate(2027, 3, 10)->setTime(12, 0));
        $this->getJson('/api/v1/settings?locale=bn')
            ->assertJsonPath('announcement', 'সাধারণ ঘোষণা')
            ->assertJsonPath('ramadan.active', false);
    }

    public function test_prayer_block_falls_back_to_the_default_city(): void
    {
        $home = app(HomeSettings::class);
        $home->sections = [
            ['type' => 'prayer_times', 'data' => ['enabled' => true, 'title' => 'নামাজের সময়', 'city' => 'sylhet']],
            ['type' => 'prayer_times', 'data' => ['enabled' => true, 'city' => 'atlantis']],
        ];
        $home->save();

        $this->getJson('/api/v1/home?locale=bn')->assertOk()
            ->assertJsonPath('sections.0.type', 'prayer_times')
            ->assertJsonPath('sections.0.city', 'sylhet')
            ->assertJsonPath('sections.1.city', 'dhaka');
    }

    public function test_admin_saves_ramadan_dates_and_order_staff_cannot(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('content_editor'));

        Livewire::test(ManageSeasonalSettings::class)
            ->set('data.ramadan_enabled', true)
            ->set('data.ramadan_starts_on', '2027-03-01')
            ->set('data.ramadan_ends_on', '2027-02-01')
            ->call('save')
            ->assertHasErrors(['data.ramadan_ends_on'])
            ->set('data.ramadan_ends_on', '2027-03-30')
            ->set('data.prayer_city', 'khulna')
            ->call('save')
            ->assertHasNoErrors();

        $saved = app(SeasonalSettings::class)->refresh();
        $this->assertSame('2027-03-30', $saved->ramadan_ends_on);
        $this->assertSame('khulna', $saved->prayer_city);

        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));
        $this->get(ManageSeasonalSettings::getUrl())->assertForbidden();
    }
}

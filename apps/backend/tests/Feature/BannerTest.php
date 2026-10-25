<?php

namespace Tests\Feature;

use App\Domain\Content\Enums\BannerPlacement;
use App\Domain\Content\Models\Banner;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_returns_only_live_banners_by_placement(): void
    {
        Banner::create(['placement' => BannerPlacement::Hero, 'title' => 'ঈদ', 'title_en' => 'Eid', 'image' => 'banners/a.jpg', 'link' => '/shop']);
        Banner::create(['placement' => BannerPlacement::Promo, 'title' => 'আতর', 'image' => 'banners/b.jpg']);
        Banner::create(['placement' => BannerPlacement::Hero, 'title' => 'বন্ধ', 'image' => 'banners/c.jpg', 'is_active' => false]);
        Banner::create(['placement' => BannerPlacement::Hero, 'title' => 'শেষ', 'image' => 'banners/d.jpg', 'ends_at' => now()->subDay()]);
        Banner::create(['placement' => BannerPlacement::Hero, 'title' => 'পরে', 'image' => 'banners/e.jpg', 'starts_at' => now()->addDay()]);

        $this->getJson('/api/v1/home?locale=en')
            ->assertOk()
            ->assertJsonCount(1, 'banners.hero')
            ->assertJsonPath('banners.hero.0.title', 'Eid')
            ->assertJsonPath('banners.hero.0.link', '/shop')
            ->assertJsonCount(1, 'banners.promo');
    }

    public function test_banner_admin_requires_permission(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
        $this->get('/admin/banners')->assertOk();

        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));
        $this->get('/admin/banners')->assertForbidden();
    }
}

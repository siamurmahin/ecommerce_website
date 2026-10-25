<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatalogSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Opens every admin page (lists, create and edit forms, settings pages) as a super admin and expects no crash. */
class AdminPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_page_opens(): void
    {
        $this->seed([DatabaseSeeder::class, DemoCatalogSeeder::class]); // demo products so edit forms get opened too
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        $urls = [];

        foreach ($panel->getPages() as $page) {
            $urls[] = $page::getUrl();
        }

        foreach ($panel->getResources() as $resource) {
            $pages = $resource::getPages();
            $record = $resource::getModel()::query()->first();

            foreach (['index', 'create'] as $name) {
                if (isset($pages[$name])) {
                    $urls[] = $resource::getUrl($name);
                }
            }

            foreach (['view', 'edit'] as $name) {
                if (isset($pages[$name]) && $record) {
                    $urls[] = $resource::getUrl($name, ['record' => $record]);
                }
            }
        }

        $failures = [];

        foreach (array_unique($urls) as $url) {
            $status = $this->get($url)->status();
            if ($status !== 200) {
                $failures[] = "{$status} {$url}";
            }
        }

        $this->assertSame([], $failures);
    }
}

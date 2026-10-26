<?php

namespace Tests\Feature;

use App\Settings\LanguageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_languages_endpoint_reports_mode_and_main_language(): void
    {
        $this->getJson('/api/v1/settings/languages')->assertExactJson(['mode' => 'both', 'main' => 'en']);
        $this->getJson('/api/v1/settings')->assertJsonPath('languages.main', 'en');

        app(LanguageSettings::class)->fill(['mode' => 'bn', 'default' => 'en'])->save();
        $this->getJson('/api/v1/settings/languages')->assertExactJson(['mode' => 'bn', 'main' => 'bn']);
    }
}

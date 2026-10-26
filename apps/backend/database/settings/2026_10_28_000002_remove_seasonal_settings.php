<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Ramadan mode and the prayer-times widget were removed. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (['ramadan_enabled', 'ramadan_starts_on', 'ramadan_ends_on', 'ramadan_announcement', 'ramadan_announcement_en', 'prayer_city', 'prayer_offset'] as $key) {
            $this->migrator->deleteIfExists("seasonal.{$key}");
        }
    }
};

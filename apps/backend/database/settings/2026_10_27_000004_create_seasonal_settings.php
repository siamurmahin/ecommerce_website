<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('seasonal.ramadan_enabled', false);
        $this->migrator->add('seasonal.ramadan_starts_on', null);
        $this->migrator->add('seasonal.ramadan_ends_on', null);
        $this->migrator->add('seasonal.ramadan_announcement', 'রমজান মোবারক — সেহরি ও ইফতারের আগে অর্ডার করুন');
        $this->migrator->add('seasonal.ramadan_announcement_en', 'Ramadan Mubarak — order ahead of Sehri and Iftar');
        $this->migrator->add('seasonal.prayer_city', 'dhaka');
        $this->migrator->add('seasonal.prayer_offset', 0);
    }
};

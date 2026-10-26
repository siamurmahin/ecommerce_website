<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('gift.wrap_enabled', false);
        $this->migrator->add('gift.wrap_price', 5000);
        $this->migrator->add('gift.wrap_cost', 2000);
        $this->migrator->add('gift.note_enabled', true);
        $this->migrator->add('site.show_hijri_date', false);
        $this->migrator->add('site.hijri_offset', 0);
    }
};

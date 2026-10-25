<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('feeds.enabled', false);
        $this->migrator->add('feeds.include_out_of_stock', true);
        $this->migrator->add('feeds.category_ids', []);
        $this->migrator->add('feeds.locale', 'bn');
        $this->migrator->add('feeds.brand_fallback', null);
        $this->migrator->add('feeds.google_category', null);
    }
};

<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (['tiktok_pixel_id', 'tiktok_test_event_code', 'google_ads_id', 'google_ads_purchase_label', 'fb_domain_verification'] as $key) {
            $this->migrator->add("tracking.{$key}", null);
        }
        $this->migrator->addEncrypted('tracking.tiktok_access_token', null);
        $this->migrator->add('tracking.page_scripts', []);
    }
};

<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (['gtm_id', 'ga4_measurement_id', 'meta_pixel_id', 'meta_test_event_code', 'clarity_id', 'gsc_verification', 'head_scripts', 'body_start_scripts', 'body_end_scripts'] as $key) {
            $this->migrator->add("tracking.{$key}", null);
        }

        $this->migrator->addEncrypted('tracking.ga4_api_secret', null);
        $this->migrator->addEncrypted('tracking.meta_capi_token', null);
    }
};

<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('sms.stock_alert_enabled', true);
        $this->migrator->add('sms.stock_alert_template', '{product} আবার স্টকে এসেছে! অর্ডার করুন: {link} - {shop}');
    }
};

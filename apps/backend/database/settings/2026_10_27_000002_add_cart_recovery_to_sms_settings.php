<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('sms.recovery_enabled', false);
        $this->migrator->add('sms.recovery_delay_minutes', 60);
        $this->migrator->add('sms.recovery_template', '{name}, আপনার কার্টে পণ্য রয়ে গেছে। এক ক্লিকে অর্ডার শেষ করুন: {link} {coupon} — {shop}');
        $this->migrator->add('sms.recovery_coupon', null);
    }
};

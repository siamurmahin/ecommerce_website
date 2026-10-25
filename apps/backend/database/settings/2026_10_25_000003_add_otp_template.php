<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('sms.otp_template', '{code} আপনার {shop} লগইন কোড। ৫ মিনিট কার্যকর। কাউকে জানাবেন না।');
    }
};

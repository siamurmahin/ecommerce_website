<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('rewards.points_enabled', false);
        $this->migrator->add('rewards.earn_per_100', 1);
        $this->migrator->add('rewards.point_value', 100);
        $this->migrator->add('rewards.min_redeem', 50);
        $this->migrator->add('rewards.max_redeem_percent', 20);
        $this->migrator->add('rewards.referral_enabled', false);
        $this->migrator->add('rewards.referrer_points', 100);
        $this->migrator->add('rewards.referee_discount', 10000);
        $this->migrator->add('rewards.referee_min_subtotal', 50000);
    }
};

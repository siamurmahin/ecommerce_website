<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notifications.new_order_email', true);
        $this->migrator->add('notifications.emails', env('ADMIN_EMAIL'));
        $this->migrator->add('notifications.admin_bell', true);
    }
};

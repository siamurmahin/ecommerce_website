<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notifications.customer_emails', true);
        $this->migrator->add('notifications.customer_email_events', ['placed', 'confirmed', 'shipped', 'delivered', 'cancelled']);
        $this->migrator->add('notifications.ask_email_at_checkout', true);
        $this->migrator->add('notifications.reply_to', null);
    }
};

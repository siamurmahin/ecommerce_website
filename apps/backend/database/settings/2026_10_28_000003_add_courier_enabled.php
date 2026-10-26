<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Off until the shop is ready to book with a real courier account.
        $this->migrator->add('courier.enabled', false);
    }
};

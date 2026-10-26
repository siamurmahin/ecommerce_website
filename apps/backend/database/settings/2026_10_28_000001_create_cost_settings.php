<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('costs.shared', []);
        $this->migrator->add('costs.owner_pays_free_delivery', true);
    }
};

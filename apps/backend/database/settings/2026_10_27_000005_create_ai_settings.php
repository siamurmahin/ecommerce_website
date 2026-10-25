<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ai.enabled', false);
        $this->migrator->add('ai.driver', 'claude');
        $this->migrator->addEncrypted('ai.api_key', null);
        $this->migrator->add('ai.model', 'claude-opus-5-5');
        $this->migrator->add('ai.brand_voice', null);
    }
};

<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('payment.cod_enabled', true);
        $this->migrator->add('payment.cod_advance', 'none');
        $this->migrator->add('payment.wallets', [
            'bkash' => ['enabled' => false, 'number' => null, 'type' => 'personal', 'instructions' => null],
            'nagad' => ['enabled' => false, 'number' => null, 'type' => 'personal', 'instructions' => null],
            'rocket' => ['enabled' => false, 'number' => null, 'type' => 'personal', 'instructions' => null],
        ]);
    }
};

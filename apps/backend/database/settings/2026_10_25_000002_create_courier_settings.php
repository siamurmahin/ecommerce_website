<?php

use Illuminate\Support\Str;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('courier.default_courier', null);
        $this->migrator->add('courier.auto_update_order', true);
        $this->migrator->addEncrypted('courier.webhook_token', Str::random(40));
        $this->migrator->addEncrypted('courier.steadfast_api_key', null);
        $this->migrator->addEncrypted('courier.steadfast_secret_key', null);
        $this->migrator->add('courier.pathao_sandbox', false);
        $this->migrator->add('courier.pathao_client_id', null);
        $this->migrator->addEncrypted('courier.pathao_client_secret', null);
        $this->migrator->add('courier.pathao_username', null);
        $this->migrator->addEncrypted('courier.pathao_password', null);
        $this->migrator->add('courier.pathao_store_id', null);
        $this->migrator->add('courier.redx_sandbox', false);
        $this->migrator->addEncrypted('courier.redx_token', null);
        $this->migrator->add('courier.redx_pickup_store_id', null);
        $this->migrator->add('courier.fraud_driver', 'internal');
        $this->migrator->addEncrypted('courier.fraud_api_key', null);
        $this->migrator->add('courier.fraud_auto_check', true);
        $this->migrator->add('courier.fraud_risky_below', 70);
        $this->migrator->add('courier.fraud_cache_hours', 24);
        $this->migrator->add('shipping.capture_incomplete_orders', true);
    }
};

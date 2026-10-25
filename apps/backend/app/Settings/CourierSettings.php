<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Courier credentials and fraud-check options, edited under সেটিংস → কুরিয়ার. */
class CourierSettings extends Settings
{
    /** Key from config('courier.couriers'), preselected in the booking dialog. */
    public ?string $default_courier;

    /** Courier status pushes/polls move the order to shipped / delivered / returned. */
    public bool $auto_update_order;

    /** Shared secret couriers send back with status pushes. */
    public string $webhook_token;

    public ?string $steadfast_api_key;

    public ?string $steadfast_secret_key;

    public bool $pathao_sandbox;

    public ?string $pathao_client_id;

    public ?string $pathao_client_secret;

    public ?string $pathao_username;

    public ?string $pathao_password;

    public ?int $pathao_store_id;

    public bool $redx_sandbox;

    public ?string $redx_token;

    public ?int $redx_pickup_store_id;

    /** Key from config('courier.fraud_checkers'). */
    public string $fraud_driver;

    public ?string $fraud_api_key;

    /** Check every new order's phone in the background. */
    public bool $fraud_auto_check;

    /** Success ratio (%) below which an order is flagged risky. */
    public int $fraud_risky_below;

    public int $fraud_cache_hours;

    public static function group(): string
    {
        return 'courier';
    }

    public static function encrypted(): array
    {
        return ['webhook_token', 'steadfast_api_key', 'steadfast_secret_key', 'pathao_client_secret', 'pathao_password', 'redx_token', 'fraud_api_key'];
    }
}

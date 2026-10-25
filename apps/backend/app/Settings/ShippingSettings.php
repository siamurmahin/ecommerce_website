<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ShippingSettings extends Settings
{
    /** Orders at or above this subtotal (poisha) ship free; 0 turns it off. */
    public int $free_delivery_threshold;

    /** Save checkout details as an incomplete order once a valid phone is typed. */
    public bool $capture_incomplete_orders;

    public static function group(): string
    {
        return 'shipping';
    }
}

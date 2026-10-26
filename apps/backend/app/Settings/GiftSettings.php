<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Gift wrap and a gift note at checkout (সেটিংস → গিফট র‍্যাপ). Money in poisha. */
class GiftSettings extends Settings
{
    public bool $wrap_enabled;

    /** Added to the order total. */
    public int $wrap_price;

    /** What one wrap costs the shop (profit report only). */
    public int $wrap_cost;

    public bool $note_enabled;

    public static function group(): string
    {
        return 'gift';
    }
}

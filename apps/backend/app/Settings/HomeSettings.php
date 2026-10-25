<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Home page layout, edited under কনটেন্ট → হোমপেজ বিল্ডার: an ordered list of
 * `{type, data}` blocks (Filament Builder format). See HomeController for each type's fields.
 */
class HomeSettings extends Settings
{
    // Each item: ['type' => string, 'data' => array]. No docblock here: spatie parses it as a cast type.
    public array $sections;

    public static function group(): string
    {
        return 'home';
    }
}

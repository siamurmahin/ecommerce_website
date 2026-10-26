<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Which storefront languages are on (সেটিংস → ভাষা); the storefront's proxy redirects to match. */
class LanguageSettings extends Settings
{
    public const MODES = ['both' => 'বাংলা ও ইংরেজি দুটোই', 'en' => 'শুধু ইংরেজি', 'bn' => 'শুধু বাংলা'];

    /** both | en | bn */
    public string $mode;

    /** With both on: the language a first-time visitor sees (en | bn). */
    public string $default;

    /** The one language visitors land on. */
    public function main(): string
    {
        return $this->mode === 'both' ? $this->default : $this->mode;
    }

    public static function group(): string
    {
        return 'language';
    }
}

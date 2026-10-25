<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** AI copywriting for products, edited under সেটিংস → AI লেখা. The API key is encrypted and never leaves the backend. */
class AiSettings extends Settings
{
    public bool $enabled;

    /** Key of config('ai.drivers'). */
    public string $driver;

    public ?string $api_key;

    public string $model;

    /** Extra house-style instructions added to every request (tone, words to avoid…). */
    public ?string $brand_voice;

    public const MODELS = [
        'claude-opus-5-5' => 'Claude Opus 5.5 — সবচেয়ে ভালো লেখা',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 — দ্রুত, কম খরচ',
        'claude-haiku-5-5' => 'Claude Haiku 5.5 — সবচেয়ে কম খরচ',
    ];

    public static function group(): string
    {
        return 'ai';
    }

    public static function encrypted(): array
    {
        return ['api_key'];
    }

    public function ready(): bool
    {
        return $this->enabled && filled($this->api_key);
    }
}

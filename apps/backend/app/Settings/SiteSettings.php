<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Store contact details and storefront copy, edited under সেটিংস → সাইট সেটিংস. `_en` fields fall back to Bangla. */
class SiteSettings extends Settings
{
    public string $hotline;

    /** BD mobile (01XXXXXXXXX); empty hides WhatsApp buttons. */
    public ?string $whatsapp;

    public ?string $email;

    public ?string $address;

    public ?string $address_en;

    /** Thin bar above the header; empty hides it. */
    public ?string $announcement;

    public ?string $announcement_en;

    public ?string $footer_about;

    public ?string $footer_about_en;

    public ?string $facebook_url;

    public ?string $instagram_url;

    public ?string $youtube_url;

    public ?string $tiktok_url;

    public static function group(): string
    {
        return 'site';
    }

    public function localized(string $field): ?string
    {
        $en = "{$field}_en";

        return app()->getLocale() === 'en' && filled($this->{$en} ?? null) ? $this->{$en} : $this->{$field};
    }
}

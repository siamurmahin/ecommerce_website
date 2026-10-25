<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Analytics / ad pixels and custom scripts, edited under সেটিংস → ট্র্যাকিং ও স্ক্রিপ্ট.
 * An empty ID switches that channel off. Server-only secrets are encrypted and never leave the backend.
 */
class TrackingSettings extends Settings
{
    public ?string $gtm_id;

    public ?string $ga4_measurement_id;

    /** GA4 Measurement Protocol secret, for the server-side purchase event. */
    public ?string $ga4_api_secret;

    public ?string $meta_pixel_id;

    /** Meta Conversions API access token. */
    public ?string $meta_capi_token;

    /** Meta Events Manager → Test events code; set only while testing. */
    public ?string $meta_test_event_code;

    public ?string $tiktok_pixel_id;

    /** TikTok Events API access token. */
    public ?string $tiktok_access_token;

    /** TikTok Events Manager → Test events code; set only while testing. */
    public ?string $tiktok_test_event_code;

    /** Google Ads conversion ID (AW-XXXXXXXXX). */
    public ?string $google_ads_id;

    /** Label of the Google Ads "Purchase" conversion action. */
    public ?string $google_ads_purchase_label;

    /** Meta Business → Brand safety → Domains: the facebook-domain-verification meta tag's content. */
    public ?string $fb_domain_verification;

    public ?string $clarity_id;

    /** Google Search Console HTML-tag verification code (the `content` value). */
    public ?string $gsc_verification;

    public ?string $head_scripts;

    public ?string $body_start_scripts;

    public ?string $body_end_scripts;

    /** Snippets that load only on matching pages. Each: name, paths (one pattern per line, * wildcard), placement (head|body_end), code, enabled. */
    public array $page_scripts;

    public static function group(): string
    {
        return 'tracking';
    }

    public static function encrypted(): array
    {
        return ['ga4_api_secret', 'meta_capi_token', 'tiktok_access_token'];
    }

    public function metaServerEnabled(): bool
    {
        return filled($this->meta_pixel_id) && filled($this->meta_capi_token);
    }

    public function tiktokServerEnabled(): bool
    {
        return filled($this->tiktok_pixel_id) && filled($this->tiktok_access_token);
    }

    public function ga4ServerEnabled(): bool
    {
        return filled($this->ga4_measurement_id) && filled($this->ga4_api_secret);
    }
}

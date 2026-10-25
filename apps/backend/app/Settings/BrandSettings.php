<?php

namespace App\Settings;

use Illuminate\Support\Facades\Storage;
use Spatie\LaravelSettings\Settings;

/**
 * Brand identity and storefront theme, edited under সেটিংস → ব্র্যান্ড ও থিম.
 * Colours are #RRGGBB; fonts are keys from the lists below, each preloaded by the storefront.
 */
class BrandSettings extends Settings
{
    public const HEADING_FONTS = [
        'fraunces' => 'Fraunces (এডিটোরিয়াল সেরিফ)',
        'playfair' => 'Playfair Display (ক্লাসিক সেরিফ)',
        'dm-serif' => 'DM Serif Display (বোল্ড সেরিফ)',
        'cormorant' => 'Cormorant Garamond (এলিগ্যান্ট সেরিফ)',
        'poppins' => 'Poppins (জ্যামিতিক স্যান্স)',
        'manrope' => 'Manrope (আধুনিক স্যান্স)',
    ];

    public const BODY_FONTS = [
        'manrope' => 'Manrope',
        'inter' => 'Inter',
        'dm-sans' => 'DM Sans',
        'plus-jakarta' => 'Plus Jakarta Sans',
        'poppins' => 'Poppins',
    ];

    public const HEADING_FONTS_BN = [
        'noto-serif-bengali' => 'Noto Serif Bengali (সেরিফ)',
        'tiro-bangla' => 'Tiro Bangla (ঐতিহ্যবাহী)',
        'noto-sans-bengali' => 'Noto Sans Bengali (স্যান্স)',
        'hind-siliguri' => 'Hind Siliguri',
        'anek-bangla' => 'Anek Bangla (আধুনিক)',
    ];

    public const BODY_FONTS_BN = [
        'noto-sans-bengali' => 'Noto Sans Bengali',
        'hind-siliguri' => 'Hind Siliguri',
        'anek-bangla' => 'Anek Bangla',
        'baloo-da-2' => 'Baloo Da 2 (গোলাকার)',
    ];

    public string $name;

    public ?string $tagline;

    public ?string $tagline_en;

    /** Public-disk paths; empty falls back to the brand name as text / the default icon. */
    public ?string $logo;

    public ?string $favicon;

    public string $color_primary;

    public string $color_accent;

    public string $color_background;

    public string $color_surface;

    public string $color_text;

    public string $color_muted;

    public string $color_border;

    /** Corner radius of cards and banners, px. */
    public int $radius;

    public string $font_heading;

    public string $font_body;

    public string $font_heading_bn;

    public string $font_body_bn;

    public static function group(): string
    {
        return 'brand';
    }

    public function localized(string $field): ?string
    {
        $en = "{$field}_en";

        return app()->getLocale() === 'en' && filled($this->{$en} ?? null) ? $this->{$en} : $this->{$field};
    }

    public function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}

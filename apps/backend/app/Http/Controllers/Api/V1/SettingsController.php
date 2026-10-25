<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Settings\BrandSettings;
use App\Settings\SeasonalSettings;
use App\Settings\SiteSettings;

class SettingsController extends Controller
{
    /**
     * Public store settings for the storefront shell (brand, theme, contact, top bar, footer, socials).
     *
     * @response array{
     *   brand: array{name: string, tagline: string|null, logo: string|null, favicon: string|null},
     *   theme: array{
     *     colors: array{primary: string, accent: string, background: string, surface: string, text: string, muted: string, border: string},
     *     radius: int,
     *     fonts: array{heading: string, body: string, heading_bn: string, body_bn: string}
     *   },
     *   hotline: string,
     *   hotline_tel: string,
     *   whatsapp_url: string|null,
     *   email: string|null,
     *   address: string|null,
     *   announcement: string|null,
     *   footer_about: string|null,
     *   socials: array<int, array{network: 'facebook'|'instagram'|'youtube'|'tiktok', url: string}>,
     *   ramadan: array{active: bool, starts_on: string|null, ends_on: string|null},
     *   prayer: array{city: string, offset: int}
     * }
     */
    public function __invoke(SiteSettings $settings, BrandSettings $brand, SeasonalSettings $seasonal): array
    {
        $socials = collect(['facebook', 'instagram', 'youtube', 'tiktok'])
            ->filter(fn (string $network) => filled($settings->{"{$network}_url"}))
            ->map(fn (string $network) => ['network' => $network, 'url' => $settings->{"{$network}_url"}])
            ->values()
            ->all();

        return [
            'brand' => [
                'name' => $brand->name,
                'tagline' => $brand->localized('tagline'),
                'logo' => $brand->url($brand->logo),
                'favicon' => $brand->url($brand->favicon),
            ],
            'theme' => [
                'colors' => [
                    'primary' => $brand->color_primary,
                    'accent' => $brand->color_accent,
                    'background' => $brand->color_background,
                    'surface' => $brand->color_surface,
                    'text' => $brand->color_text,
                    'muted' => $brand->color_muted,
                    'border' => $brand->color_border,
                ],
                'radius' => $brand->radius,
                'fonts' => [
                    'heading' => $brand->font_heading,
                    'body' => $brand->font_body,
                    'heading_bn' => $brand->font_heading_bn,
                    'body_bn' => $brand->font_body_bn,
                ],
            ],
            'hotline' => $settings->hotline,
            'hotline_tel' => preg_replace('/[^0-9+]/', '', $settings->hotline),
            'whatsapp_url' => $settings->whatsapp ? 'https://wa.me/88'.$settings->whatsapp : null,
            'email' => $settings->email,
            'address' => $settings->localized('address'),
            // Ramadan mode swaps in its own top-bar message.
            'announcement' => $seasonal->ramadanActive() && filled($seasonal->announcement()) ? $seasonal->announcement() : $settings->localized('announcement'),
            'footer_about' => $settings->localized('footer_about'),
            'socials' => $socials,
            'ramadan' => [
                'active' => $seasonal->ramadanActive(),
                'starts_on' => $seasonal->ramadan_enabled ? $seasonal->ramadan_starts_on : null,
                'ends_on' => $seasonal->ramadan_enabled ? $seasonal->ramadan_ends_on : null,
            ],
            'prayer' => ['city' => $seasonal->prayer_city, 'offset' => (int) $seasonal->prayer_offset],
        ];
    }
}

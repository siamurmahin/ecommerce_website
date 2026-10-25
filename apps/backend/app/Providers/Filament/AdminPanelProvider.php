<?php

namespace App\Providers\Filament;

use App\Settings\BrandSettings;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(isSimple: false)
            // Authenticator-app codes (Google Authenticator, Authy…), with recovery codes for a lost phone.
            ->multiFactorAuthentication(
                AppAuthentication::make()->recoverable(), // issuer in the app = the panel's brand name
                isRequired: fn () => (bool) config('shop.admin_2fa_required'),
            )
            ->brandName(fn () => self::brand()?->name ?? 'Looks Jhorna')
            ->brandLogo(fn () => self::brand()?->url(self::brand()->logo))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn () => self::brand()?->url(self::brand()->favicon))
            ->font('Manrope')
            ->colors([
                'primary' => Color::hex('#0F4C3A'),
                'gray' => Color::Neutral,
            ])
            // Sidebar order.
            ->navigationGroups(['অর্ডার', 'ক্যাটালগ', 'কাস্টমার', 'মার্কেটিং', 'কনটেন্ট', 'রিপোর্ট', 'সেটিংস'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /** Null before the settings migration has run (fresh install, some console commands). */
    private static function brand(): ?BrandSettings
    {
        try {
            $brand = app(BrandSettings::class);
            $brand->name; // settings load lazily; force it here so a missing table is caught

            return $brand;
        } catch (\Throwable) {
            return null;
        }
    }
}

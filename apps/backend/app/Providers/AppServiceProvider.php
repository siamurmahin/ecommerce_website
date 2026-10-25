<?php

namespace App\Providers;

use App\Domain\Ai\Contracts\Copywriter;
use App\Domain\Ai\Drivers\ClaudeCopywriter;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Search\Contracts\SearchEngine;
use App\Domain\Catalog\Search\Drivers\DatabaseSearch;
use App\Domain\Customer\Listeners\RecordCustomer;
use App\Domain\Fraud\Listeners\CheckNewOrder;
use App\Domain\Inventory\StockLedger;
use App\Domain\Marketing\Support\ActiveFlashSales;
use App\Domain\Order\Events\OrderPlaced;
use App\Domain\Order\Events\OrderStatusChanged;
use App\Domain\Order\Listeners\CloseIncompleteOrders;
use App\Domain\Reward\Listeners\SyncOrderPoints;
use App\Domain\Sms\Listeners\SendOrderSms;
use App\Models\User;
use App\Settings\AiSettings;
use App\Settings\BrandSettings;
use App\Settings\HomeSettings;
use App\Settings\PaymentSettings;
use App\Settings\SearchSettings;
use App\Settings\SeasonalSettings;
use App\Settings\ShippingSettings;
use App\Settings\SiteSettings;
use App\Settings\TrackingSettings;
use App\Support\ClientIp;
use App\Support\StorefrontCache;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Events\SettingsSaved;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The copywriting provider picked in Admin → সেটিংস → AI লেখা.
        $this->app->bind(Copywriter::class, fn ($app) => $app->make(config('ai.drivers.'.$app->make(AiSettings::class)->driver) ?? ClaudeCopywriter::class));

        $this->app->bind(SearchEngine::class, fn ($app) => $app->make(config('search.drivers.'.config('search.driver')) ?? DatabaseSearch::class));

        $this->app->scoped(ActiveFlashSales::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Stored in UTC; staff read times in the shop's own timezone.
        FilamentTimezone::set(config('shop.timezone'));

        // Super admins pass every check, including permissions added after their role was set up.
        Gate::before(fn (User $user) => $user->hasRole('super_admin') ? true : null);

        // Login codes cost an SMS each: cap them per visitor IP on top of OtpService's per-phone limits.
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(5)->by('otp-ip:'.ClientIp::of($request)),
            Limit::perHour(20)->by('otp-ip-hour:'.ClientIp::of($request)),
        ]);
        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(15)->by('otp-verify:'.ClientIp::of($request)));

        StockLedger::observe(Product::class);
        StockLedger::observe(ProductVariant::class);
        Event::listen(OrderPlaced::class, [SendOrderSms::class, 'placed']);
        Event::listen(OrderPlaced::class, CloseIncompleteOrders::class);
        Event::listen(OrderPlaced::class, CheckNewOrder::class);
        Event::listen(OrderPlaced::class, RecordCustomer::class);
        Event::listen(OrderStatusChanged::class, [SendOrderSms::class, 'statusChanged']);
        Event::listen(OrderStatusChanged::class, SyncOrderPoints::class);

        Event::listen(SettingsSaved::class, fn (SettingsSaved $event) => match ($event->settings::class) {
            SiteSettings::class, BrandSettings::class, SeasonalSettings::class => StorefrontCache::revalidate('settings'),
            // Checkout options (zones + payment methods) are cached under the shipping tag.
            ShippingSettings::class, PaymentSettings::class => StorefrontCache::revalidate('shipping'),
            TrackingSettings::class => StorefrontCache::revalidate('tracking'),
            HomeSettings::class => StorefrontCache::revalidate('home'),
            // Synonyms change which products a cached search page lists.
            SearchSettings::class => StorefrontCache::revalidate('products'),
            default => null,
        });
    }
}

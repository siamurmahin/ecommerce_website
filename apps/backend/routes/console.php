<?php

use App\Support\StorefrontCache;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('courier:sync')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('model:prune')->daily();
Schedule::command('carts:remind')->everyTenMinutes()->withoutOverlapping();
Schedule::command('flash-sales:refresh')->everyMinute();
Schedule::command('sanctum:prune-expired --hours=24')->daily(); // expired customer login tokens

// Date-driven storefront copy (Ramadan mode's top bar) changes at local midnight.
Schedule::call(fn () => StorefrontCache::revalidate('settings'))->name('storefront:midnight')->dailyAt('00:01')->timezone(config('shop.timezone'));

<?php

namespace App\Console\Commands;

use App\Domain\Marketing\Models\FlashSale;
use App\Support\StorefrontCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('flash-sales:refresh')]
#[Description('Refresh storefront prices when a flash sale has just started or ended')]
class RefreshFlashSales extends Command
{
    public function handle(): void
    {
        $since = Cache::get('flash-sales:last-refresh', now()->subMinutes(2));
        $now = now();

        $changed = FlashSale::where('is_active', true)
            ->where(fn ($q) => $q->whereBetween('starts_at', [$since, $now])->orWhereBetween('ends_at', [$since, $now]))
            ->exists();

        // Weekly sales (জুম্মার অফার) that just ended move on to next week.
        FlashSale::where('is_active', true)->where('repeats_weekly', true)->where('ends_at', '<=', $now)->get()->each->rollForward();

        Cache::put('flash-sales:last-refresh', $now, now()->addDay());

        if ($changed) {
            StorefrontCache::revalidate('catalog', 'home');
            StorefrontCache::send(); // console has no response to defer to
            $this->info('Storefront refreshed.');
        }
    }
}

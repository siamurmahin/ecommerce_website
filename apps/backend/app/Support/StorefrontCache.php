<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Tells the Next.js storefront which cache tags went stale (see storefront app/api/revalidate).
 * Tags are collected during the request and sent in one POST after the response.
 */
final class StorefrontCache
{
    /** @var list<string> */
    private static array $pending = [];

    public static function revalidate(string ...$tags): void
    {
        if (blank(config('shop.revalidate_secret')) || $tags === []) {
            return;
        }

        self::$pending = array_values(array_unique([...self::$pending, ...$tags]));

        // Same name → later calls replace the earlier callback, so a bulk save still sends once.
        defer(fn () => self::send(), 'storefront-revalidate');
    }

    public static function send(): void
    {
        [$tags, self::$pending] = [self::$pending, []];

        if ($tags === []) {
            return;
        }

        try {
            Http::timeout(3)
                ->withHeaders(['X-Revalidate-Secret' => config('shop.revalidate_secret')])
                ->post(rtrim((string) config('shop.storefront_url'), '/').'/api/revalidate', ['tags' => $tags])
                ->throw();
        } catch (Throwable $e) {
            // The storefront still refreshes on its own cache lifetime; never fail the admin save for this.
            Log::warning('Storefront revalidate failed', ['tags' => $tags, 'error' => $e->getMessage()]);
        }
    }
}

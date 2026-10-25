<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Feeds\ProductFeed;
use App\Settings\FeedSettings;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** Public catalog feeds fetched on a schedule by Meta and Google. */
class FeedController extends Controller
{
    public const CACHE_KEY = 'product-feed:';

    public function __invoke(string $channel, FeedSettings $settings, ProductFeed $feed): Response
    {
        abort_unless($settings->enabled && array_key_exists($channel, ProductFeed::CHANNELS), 404);

        // Short cache: stock and flash-sale prices stay close to live without rebuilding on every fetch.
        $xml = Cache::remember(self::CACHE_KEY.$channel, now()->addMinutes(15), fn () => $feed->xml($channel));

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}

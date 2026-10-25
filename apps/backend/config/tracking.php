<?php

use App\Domain\Tracking\Drivers\Ga4MeasurementProtocol;
use App\Domain\Tracking\Drivers\MetaConversionsApi;
use App\Domain\Tracking\Drivers\TikTokEventsApi;

return [
    /*
    | Server-side event destinations (each implements ServerEventDriver). IDs and secrets live in
    | admin → সেটিংস → ট্র্যাকিং ও স্ক্রিপ্ট; a driver without them is skipped.
    */
    'drivers' => [
        MetaConversionsApi::class,
        Ga4MeasurementProtocol::class,
        TikTokEventsApi::class,
    ],

    'tiktok_api_url' => env('TIKTOK_EVENTS_API_URL', 'https://business-api.tiktok.com/open_api/v1.3/event/track/'),

    'meta_graph_version' => env('META_GRAPH_VERSION', 'v23.0'),
];

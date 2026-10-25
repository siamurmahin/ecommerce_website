<?php

// Deployment config only; store details live in App\Settings\SiteSettings (admin → সাইট সেটিংস).
return [
    'storefront_url' => env('STOREFRONT_URL', 'http://localhost:3000'),
    /** Shared with the storefront's REVALIDATE_SECRET; empty turns cache refresh off. */
    'revalidate_secret' => env('STOREFRONT_REVALIDATE_SECRET'),
    /** Timestamps are stored in UTC; admin screens and day-wise reports use the shop's local time. */
    'timezone' => env('SHOP_TIMEZONE', 'Asia/Dhaka'),
    /** Staff must set up an authenticator app before using the admin. On by default in production. */
    'admin_2fa_required' => (bool) env('ADMIN_REQUIRE_2FA', env('APP_ENV') === 'production'),
];

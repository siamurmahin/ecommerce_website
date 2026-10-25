<?php

use App\Domain\Courier\Drivers\PathaoCourier;
use App\Domain\Courier\Drivers\RedxCourier;
use App\Domain\Courier\Drivers\SteadfastCourier;
use App\Domain\Fraud\Drivers\BdCourierChecker;
use App\Domain\Fraud\Drivers\InternalHistoryChecker;

return [
    /* Key => Courier class. Credentials live in admin → সেটিংস → কুরিয়ার। */
    'couriers' => [
        'steadfast' => SteadfastCourier::class,
        'pathao' => PathaoCourier::class,
        'redx' => RedxCourier::class,
    ],

    /* Key => FraudChecker class (phone number delivery history). */
    'fraud_checkers' => [
        'internal' => InternalHistoryChecker::class,
        'bdcourier' => BdCourierChecker::class,
    ],

    'steadfast' => [
        'base_url' => env('STEADFAST_BASE_URL', 'https://portal.packzy.com/api/v1'),
    ],

    'pathao' => [
        'base_url' => env('PATHAO_BASE_URL', 'https://api-hermes.pathao.com'),
        'sandbox_url' => 'https://courier-api-sandbox.pathao.com',
        // Pathao's webhook setup checks for this header in our reply (value from their merchant API docs).
        'webhook_integration_secret' => env('PATHAO_WEBHOOK_INTEGRATION_SECRET', 'f3992ecc-59da-4cbe-a049-a13da2018d51'),
    ],

    'redx' => [
        'base_url' => env('REDX_BASE_URL', 'https://openapi.redx.com.bd/v1.0.0-beta'),
        'sandbox_url' => 'https://sandbox.redx.com.bd/v1.0.0-beta',
    ],

    'bdcourier' => [
        'url' => env('BDCOURIER_URL', 'https://api.bdcourier.com/courier-check'),
    ],
];

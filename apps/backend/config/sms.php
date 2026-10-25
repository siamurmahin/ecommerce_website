<?php

use App\Domain\Sms\Drivers\BulkSmsBdGateway;
use App\Domain\Sms\Drivers\LogGateway;
use App\Domain\Sms\Drivers\SslWirelessGateway;

return [
    /* Key => SmsGateway class. The active one is picked in admin → সেটিংস → SMS. */
    'gateways' => [
        'log' => LogGateway::class,
        'bulksmsbd' => BulkSmsBdGateway::class,
        'sslwireless' => SslWirelessGateway::class,
    ],
];

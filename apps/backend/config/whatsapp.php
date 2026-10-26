<?php

use App\Domain\WhatsApp\Drivers\LogGateway;
use App\Domain\WhatsApp\Drivers\MetaCloudGateway;

return [
    /* Key => WhatsAppGateway class. The active one is picked in admin → সেটিংস → WhatsApp. */
    'gateways' => [
        'log' => LogGateway::class,
        'meta' => MetaCloudGateway::class,
    ],
];

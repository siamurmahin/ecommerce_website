<?php

use App\Domain\Ai\Drivers\ClaudeCopywriter;

return [
    // Copywriting providers (Admin → সেটিংস → AI লেখা picks one). Add a provider by adding a class here.
    'drivers' => [
        'claude' => ClaudeCopywriter::class,
    ],
];

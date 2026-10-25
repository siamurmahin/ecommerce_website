<?php

namespace App\Domain\Courier\Data;

final readonly class WebhookUpdate
{
    public function __construct(
        public string $consignmentId,
        public string $rawStatus,
    ) {}
}

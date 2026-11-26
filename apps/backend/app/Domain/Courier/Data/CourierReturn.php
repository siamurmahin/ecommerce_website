<?php

namespace App\Domain\Courier\Data;

/** A return pickup the courier has on file. */
final readonly class CourierReturn
{
    public function __construct(
        public string $id,
        public string $status,
    ) {}
}

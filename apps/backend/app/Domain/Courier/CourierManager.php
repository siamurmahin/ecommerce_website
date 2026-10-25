<?php

namespace App\Domain\Courier;

use App\Domain\Courier\Contracts\Courier;
use InvalidArgumentException;

final class CourierManager
{
    public function driver(string $key): Courier
    {
        $class = config("courier.couriers.{$key}") ?? throw new InvalidArgumentException("Unknown courier [{$key}]");

        return app($class);
    }

    /** @return array<string, string> key => label for every courier */
    public function labels(): array
    {
        return collect(config('courier.couriers'))->map(fn (string $class) => $class::label())->all();
    }

    /** @return array<string, string> key => label for couriers with credentials */
    public function configured(): array
    {
        return collect($this->labels())->filter(fn ($label, string $key) => $this->driver($key)->isConfigured())->all();
    }
}

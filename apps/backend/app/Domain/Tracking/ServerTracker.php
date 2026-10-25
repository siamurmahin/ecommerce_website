<?php

namespace App\Domain\Tracking;

use App\Domain\Tracking\Contracts\ServerEventDriver;
use App\Domain\Tracking\Jobs\SendServerEvent;

/** Fans an event out to every configured driver, one queued job each. */
final class ServerTracker
{
    public function dispatch(ServerEvent $event): void
    {
        foreach ($this->drivers() as $class => $driver) {
            if ($driver->enabled() && $driver->supports($event->name)) {
                SendServerEvent::dispatch($class, $event)->afterCommit();
            }
        }
    }

    /** True when at least one driver would accept this event, so callers can skip the work. */
    public function wants(string $event): bool
    {
        foreach ($this->drivers() as $driver) {
            if ($driver->enabled() && $driver->supports($event)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<class-string<ServerEventDriver>, ServerEventDriver> */
    private function drivers(): array
    {
        return collect(config('tracking.drivers'))->mapWithKeys(fn (string $class) => [$class => app($class)])->all();
    }
}

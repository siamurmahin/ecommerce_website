<?php

namespace App\Domain\Tracking\Jobs;

use App\Domain\Tracking\Contracts\ServerEventDriver;
use App\Domain\Tracking\ServerEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** One event to one driver, so a retry never re-sends to a destination that already accepted it. */
class SendServerEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /** @param  class-string<ServerEventDriver>  $driver */
    public function __construct(public string $driver, public ServerEvent $event) {}

    public function handle(): void
    {
        $driver = app($this->driver);

        if ($driver->enabled()) {
            $driver->send($this->event);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Server tracking event failed', [
            'driver' => class_basename($this->driver),
            'event' => $this->event->name,
            'event_id' => $this->event->eventId,
            'error' => $e->getMessage(),
        ]);
    }
}

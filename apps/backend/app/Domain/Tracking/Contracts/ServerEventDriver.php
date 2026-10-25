<?php

namespace App\Domain\Tracking\Contracts;

use App\Domain\Tracking\ServerEvent;

/** A server-side analytics / ads destination. Add a provider by adding a class and listing it in `config/tracking.php`. */
interface ServerEventDriver
{
    /** Configured in admin (IDs and secrets present). */
    public function enabled(): bool;

    public function supports(string $event): bool;

    /** Throws on a failed delivery so the job can retry. */
    public function send(ServerEvent $event): void;
}

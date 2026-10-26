<?php

namespace App\Console\Commands;

use App\Domain\Courier\Jobs\SyncShipment;
use App\Domain\Courier\Models\Shipment;
use App\Settings\CourierSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('courier:sync {--minutes=60 : Skip shipments synced more recently than this}')]
#[Description('Poll couriers for shipments that are still on the way')]
class SyncShipments extends Command
{
    public function handle(CourierSettings $settings): void
    {
        if (! $settings->enabled) {
            $this->info('Courier API is off (সেটিংস → কুরিয়ার).');

            return;
        }

        $count = 0;

        Shipment::active()
            ->where('created_at', '>=', now()->subDays(45))
            ->where(fn ($q) => $q->whereNull('synced_at')->orWhere('synced_at', '<=', now()->subMinutes((int) $this->option('minutes'))))
            ->select('id')
            ->each(function (Shipment $shipment) use (&$count) {
                SyncShipment::dispatch($shipment->id);
                $count++;
            });

        $this->info("{$count} shipment(s) queued.");
    }
}

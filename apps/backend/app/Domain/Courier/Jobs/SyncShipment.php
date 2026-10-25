<?php

namespace App\Domain\Courier\Jobs;

use App\Domain\Courier\Actions\ApplyShipmentStatus;
use App\Domain\Courier\CourierException;
use App\Domain\Courier\Models\Shipment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Poll one shipment; the backstop for missed webhooks. */
class SyncShipment implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $shipmentId) {}

    public function handle(ApplyShipmentStatus $apply): void
    {
        $shipment = Shipment::with('order')->find($this->shipmentId);
        if (! $shipment || $shipment->status->isFinal()) {
            return;
        }

        try {
            $apply->handle($shipment, $shipment->driver()->fetchStatus($shipment));
        } catch (CourierException $e) {
            $shipment->update(['synced_at' => now()]);
            Log::warning('Courier sync failed', ['shipment' => $shipment->id, 'error' => $e->getMessage()]);
        }
    }
}

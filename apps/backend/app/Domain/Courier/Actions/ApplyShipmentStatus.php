<?php

namespace App\Domain\Courier\Actions;

use App\Domain\Courier\Models\Shipment;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Settings\CourierSettings;

/** A courier status (from a webhook or a poll) lands on the shipment and, if enabled, the order. */
final class ApplyShipmentStatus
{
    public function __construct(private ChangeOrderStatus $changeStatus, private CourierSettings $settings) {}

    public function handle(Shipment $shipment, string $raw): void
    {
        $status = $shipment->driver()->mapStatus($raw);
        $changed = $shipment->raw_status !== $raw;

        $shipment->update(['raw_status' => $raw, 'status' => $status, 'synced_at' => now()]);

        $order = $shipment->order;
        $target = $status->orderStatus();

        // Only the newest shipment drives the order; an old cancelled booking must not return a rebooked order.
        $isLatest = $order && $order->shipments()->latest('id')->value('id') === $shipment->id;

        if ($changed && $isLatest && $target && $order->status !== $target && $this->settings->auto_update_order) {
            $this->changeStatus->handle($order, $target, "{$shipment->courierLabel()}: {$raw}");
        }
    }
}

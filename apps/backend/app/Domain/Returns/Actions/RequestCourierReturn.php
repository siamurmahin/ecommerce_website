<?php

namespace App\Domain\Returns\Actions;

use App\Domain\Courier\Contracts\TakesReturns;
use App\Domain\Courier\CourierException;
use App\Domain\Courier\Models\Shipment;
use App\Domain\Returns\Models\OrderReturn;
use App\Settings\CourierSettings;

/** Ask the courier that delivered the order to pick the parcel back up, and follow that request's status. */
final class RequestCourierReturn
{
    /** The delivered shipment whose courier can take this return over its API, if any. */
    public static function shipmentFor(OrderReturn $return): ?Shipment
    {
        if (! app(CourierSettings::class)->enabled || ! $return->order) {
            return null;
        }

        $shipment = $return->order->shipments()->latest('id')->first();

        return $shipment && $shipment->driver()->isConfigured() && $shipment->driver() instanceof TakesReturns ? $shipment : null;
    }

    /** @throws CourierException */
    public function handle(OrderReturn $return, ?string $reason = null): OrderReturn
    {
        if ($return->courier_return_id) {
            throw new CourierException('এই রিটার্নের জন্য কুরিয়ারে আগেই অনুরোধ পাঠানো হয়েছে।');
        }

        $shipment = self::shipmentFor($return) ?? throw new CourierException('এই অর্ডারের কুরিয়ার API দিয়ে রিটার্ন নেওয়া যায় না।');

        /** @var TakesReturns $driver */
        $driver = $shipment->driver();
        $request = $driver->requestReturn($shipment, $reason ?: collect([$return->reason->getLabel(), $return->note])->filter()->implode('. '));

        $return->update(['courier_return_id' => $request->id, 'courier_return_status' => $request->status]);

        return $return;
    }

    /** @throws CourierException */
    public function refresh(OrderReturn $return): OrderReturn
    {
        $shipment = $return->courier_return_id ? self::shipmentFor($return) : null;
        if (! $shipment) {
            return $return;
        }

        /** @var TakesReturns $driver */
        $driver = $shipment->driver();
        $return->update(['courier_return_status' => $driver->returnStatus($return->courier_return_id)->status]);

        return $return;
    }
}

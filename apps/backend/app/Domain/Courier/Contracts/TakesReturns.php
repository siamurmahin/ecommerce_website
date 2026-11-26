<?php

namespace App\Domain\Courier\Contracts;

use App\Domain\Courier\CourierException;
use App\Domain\Courier\Data\CourierReturn;
use App\Domain\Courier\Models\Shipment;

/** A courier that can be asked over its API to bring a delivered parcel back. */
interface TakesReturns
{
    /** @throws CourierException */
    public function requestReturn(Shipment $shipment, string $reason): CourierReturn;

    /** @throws CourierException */
    public function returnStatus(string $id): CourierReturn;
}

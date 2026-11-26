<?php

namespace App\Domain\Courier\Contracts;

use App\Domain\Courier\CourierException;
use App\Domain\Courier\Data\Payout;

/** A courier whose cash-on-delivery payouts (and the parcels in each) can be read over its API. */
interface PaysOut
{
    /**
     * Recent payouts, newest first, without their parcels.
     *
     * @return list<Payout>
     *
     * @throws CourierException
     */
    public function payouts(): array;

    /** One payout with its parcels. @throws CourierException */
    public function payout(string $id): Payout;
}

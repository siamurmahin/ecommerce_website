<?php

namespace App\Domain\Courier\Contracts;

use App\Domain\Courier\CourierException;
use App\Domain\Courier\Data\Booking;
use App\Domain\Courier\Data\Parcel;
use App\Domain\Order\Models\Order;

/** A courier that books many parcels in one request. */
interface BooksInBulk
{
    /** Most parcels one request may carry. */
    public function bulkLimit(): int;

    /**
     * @param  list<array{0: Order, 1: Parcel}>  $parcels  at most bulkLimit()
     * @return array<int, Booking|string> order id => booking, or the courier's reason it was refused
     *
     * @throws CourierException when the whole request fails
     */
    public function bookMany(array $parcels): array;
}

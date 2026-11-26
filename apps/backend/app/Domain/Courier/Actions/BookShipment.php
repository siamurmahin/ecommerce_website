<?php

namespace App\Domain\Courier\Actions;

use App\Domain\Courier\Contracts\BooksInBulk;
use App\Domain\Courier\Contracts\Courier;
use App\Domain\Courier\CourierException;
use App\Domain\Courier\CourierManager;
use App\Domain\Courier\Data\Booking;
use App\Domain\Courier\Data\Parcel;
use App\Domain\Courier\Models\Shipment;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use Closure;

/** Hand an order to a courier: creates the consignment, records it, marks the order shipped. */
final class BookShipment
{
    public function __construct(private CourierManager $couriers, private ChangeOrderStatus $changeStatus) {}

    /** @throws CourierException */
    public function handle(Order $order, string $courier, Parcel $parcel, ?int $userId = null): Shipment
    {
        $this->assertBookable($order);

        $driver = $this->couriers->driver($courier);

        return $this->record($order, $courier, $driver, $driver->book($order, $parcel), $parcel, $userId);
    }

    /**
     * Book many orders: in one request per batch when the courier can, otherwise one by one.
     *
     * @param  iterable<Order>  $orders
     * @param  Closure(Order): Parcel  $parcelFor
     * @return array{booked: list<Shipment>, errors: list<string>}
     */
    public function handleMany(iterable $orders, string $courier, Closure $parcelFor, ?int $userId = null): array
    {
        $driver = $this->couriers->driver($courier);
        $result = ['booked' => [], 'errors' => []];

        if (! $driver instanceof BooksInBulk) {
            foreach ($orders as $order) {
                try {
                    $result['booked'][] = $this->handle($order, $courier, $parcelFor($order), $userId);
                } catch (CourierException $e) {
                    $result['errors'][] = $e->getMessage();
                }
            }

            return $result;
        }

        $parcels = [];
        foreach ($orders as $order) {
            try {
                $this->assertBookable($order);
                $parcels[] = [$order, $parcelFor($order)];
            } catch (CourierException $e) {
                $result['errors'][] = $e->getMessage();
            }
        }

        foreach (array_chunk($parcels, $driver->bulkLimit()) as $batch) {
            try {
                $bookings = $driver->bookMany($batch);
            } catch (CourierException $e) {
                $result['errors'][] = $e->getMessage();

                continue;
            }

            foreach ($batch as [$order, $parcel]) {
                $booking = $bookings[$order->id] ?? null;
                if ($booking instanceof Booking) {
                    $result['booked'][] = $this->record($order, $courier, $driver, $booking, $parcel, $userId);
                } else {
                    $result['errors'][] = "{$order->number}: ".($booking ?? 'বুক হয়নি');
                }
            }
        }

        return $result;
    }

    /** @throws CourierException */
    private function assertBookable(Order $order): void
    {
        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Returned, OrderStatus::Delivered], true)) {
            throw new CourierException("অর্ডার {$order->number} ({$order->status->getLabel()}) কুরিয়ারে পাঠানো যায় না।");
        }

        if ($active = $order->shipments()->active()->first()) {
            throw new CourierException("অর্ডার {$order->number} আগেই {$active->courierLabel()}-এ বুক করা আছে ({$active->consignment_id})।");
        }
    }

    private function record(Order $order, string $courier, Courier $driver, Booking $booking, Parcel $parcel, ?int $userId): Shipment
    {
        $shipment = $order->shipments()->create([
            'courier' => $courier,
            'consignment_id' => $booking->consignmentId,
            'tracking_code' => $booking->trackingCode,
            'raw_status' => $booking->rawStatus,
            'status' => $driver->mapStatus($booking->rawStatus),
            'cod_amount' => $parcel->codAmount,
            'delivery_fee' => $booking->deliveryFee,
            'user_id' => $userId,
            'payload' => $booking->payload,
            'synced_at' => now(),
        ]);

        if ($order->status !== OrderStatus::Shipped) {
            $this->changeStatus->handle($order, OrderStatus::Shipped, "{$driver::label()} · {$booking->consignmentId}", $userId);
        }

        return $shipment;
    }
}

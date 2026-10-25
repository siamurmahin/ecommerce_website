<?php

namespace App\Domain\Courier\Actions;

use App\Domain\Courier\CourierException;
use App\Domain\Courier\CourierManager;
use App\Domain\Courier\Data\Parcel;
use App\Domain\Courier\Models\Shipment;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;

/** Hand an order to a courier: creates the consignment, records it, marks the order shipped. */
final class BookShipment
{
    public function __construct(private CourierManager $couriers, private ChangeOrderStatus $changeStatus) {}

    /** @throws CourierException */
    public function handle(Order $order, string $courier, Parcel $parcel, ?int $userId = null): Shipment
    {
        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Returned, OrderStatus::Delivered], true)) {
            throw new CourierException("অর্ডার {$order->number} ({$order->status->getLabel()}) কুরিয়ারে পাঠানো যায় না।");
        }

        if ($active = $order->shipments()->active()->first()) {
            throw new CourierException("অর্ডার {$order->number} আগেই {$active->courierLabel()}-এ বুক করা আছে ({$active->consignment_id})।");
        }

        $driver = $this->couriers->driver($courier);
        $booking = $driver->book($order, $parcel);

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

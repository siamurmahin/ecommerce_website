<?php

namespace App\Domain\Courier\Enums;

use App\Domain\Order\Enums\OrderStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Every courier's own status words are mapped onto these. */
enum ShipmentStatus: string implements HasColor, HasLabel
{
    case Booked = 'booked';
    case InTransit = 'in_transit';
    case Hold = 'hold';
    case Delivered = 'delivered';
    case PartialDelivered = 'partial_delivered';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Booked => 'বুকড',
            self::InTransit => 'পথে',
            self::Hold => 'হোল্ড',
            self::Delivered => 'ডেলিভারড',
            self::PartialDelivered => 'আংশিক ডেলিভারড',
            self::Cancelled => 'বাতিল/রিটার্ন',
            self::Unknown => 'অজানা',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Booked, self::InTransit => 'info',
            self::Hold, self::Unknown => 'warning',
            self::Delivered => 'success',
            self::PartialDelivered => 'primary',
            self::Cancelled => 'danger',
        };
    }

    /** No more updates expected; polling stops. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::PartialDelivered, self::Cancelled], true);
    }

    /** The order status this shipment status implies, or null to leave the order alone. */
    public function orderStatus(): ?OrderStatus
    {
        return match ($this) {
            self::Booked, self::InTransit => OrderStatus::Shipped,
            self::Delivered, self::PartialDelivered => OrderStatus::Delivered,
            self::Cancelled => OrderStatus::Returned,
            self::Hold, self::Unknown => null,
        };
    }
}

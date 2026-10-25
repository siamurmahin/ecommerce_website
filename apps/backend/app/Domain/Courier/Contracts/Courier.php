<?php

namespace App\Domain\Courier\Contracts;

use App\Domain\Courier\CourierException;
use App\Domain\Courier\Data\Booking;
use App\Domain\Courier\Data\Parcel;
use App\Domain\Courier\Data\WebhookUpdate;
use App\Domain\Courier\Enums\ShipmentStatus;
use App\Domain\Courier\Models\Shipment;
use App\Domain\Order\Models\Order;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A courier company. Add one by adding a class and listing it in `config/courier.php`. */
interface Courier
{
    public static function label(): string;

    /** Credentials are filled in under সেটিংস → কুরিয়ার. */
    public function isConfigured(): bool;

    /** Cheap authenticated call for the "test connection" button; returns something to show (balance, store name). */
    public function test(): string;

    /**
     * Couriers that need their own delivery-area id return [id => name] for this order's district.
     *
     * @return array<int|string, string>|null
     */
    public function areaOptions(Order $order): ?array;

    /** @throws CourierException */
    public function book(Order $order, Parcel $parcel): Booking;

    /** @throws CourierException */
    public function fetchStatus(Shipment $shipment): string;

    public function mapStatus(string $raw): ShipmentStatus;

    /**
     * Verify and read a status push. Null means "valid, but nothing to apply" (e.g. a tracking note).
     *
     * @throws AuthorizationException when the request isn't from the courier
     */
    public function parseWebhook(Request $request): ?WebhookUpdate;

    /** Some couriers expect particular headers back. */
    public function webhookResponse(): Response;

    public function trackingUrl(Shipment $shipment): ?string;
}

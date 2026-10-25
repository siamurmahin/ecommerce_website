<?php

namespace App\Domain\Courier\Drivers;

use App\Domain\Courier\Contracts\Courier;
use App\Domain\Courier\CourierException;
use App\Domain\Courier\Data\Booking;
use App\Domain\Courier\Data\Parcel;
use App\Domain\Courier\Data\WebhookUpdate;
use App\Domain\Courier\Drivers\Concerns\CourierHelpers;
use App\Domain\Courier\Enums\ShipmentStatus;
use App\Domain\Courier\Models\Shipment;
use App\Domain\Order\Models\Order;
use App\Settings\CourierSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Steadfast (packzy) merchant API: Api-Key + Secret-Key headers, webhook with a Bearer token. */
final class SteadfastCourier implements Courier
{
    use CourierHelpers;

    public function __construct(private CourierSettings $settings) {}

    public static function label(): string
    {
        return 'Steadfast';
    }

    public function isConfigured(): bool
    {
        return filled($this->settings->steadfast_api_key) && filled($this->settings->steadfast_secret_key);
    }

    public function test(): string
    {
        $response = $this->http()->get('/get_balance');
        if (! $response->successful() || (int) $response->json('status') !== 200) {
            $this->fail($response, self::label());
        }

        return 'ব্যালেন্স: ৳'.number_format((float) $response->json('current_balance'));
    }

    public function areaOptions(Order $order): ?array
    {
        return null;
    }

    public function book(Order $order, Parcel $parcel): Booking
    {
        $response = $this->http()->post('/create_order', [
            'invoice' => $order->number,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'recipient_address' => mb_substr($this->recipientAddress($order), 0, 250),
            'cod_amount' => $parcel->codTaka(),
            'note' => $parcel->note,
            'item_description' => mb_substr($this->itemDescription($order), 0, 250),
        ]);

        $consignment = $response->json('consignment');
        if (! $response->successful() || (int) $response->json('status') !== 200 || ! isset($consignment['consignment_id'])) {
            $this->fail($response, self::label());
        }

        return new Booking(
            consignmentId: (string) $consignment['consignment_id'],
            trackingCode: $consignment['tracking_code'] ?? null,
            rawStatus: $consignment['status'] ?? 'in_review',
            payload: $consignment,
        );
    }

    public function fetchStatus(Shipment $shipment): string
    {
        $response = $this->http()->get("/status_by_cid/{$shipment->consignment_id}");
        if (! $response->successful() || ! $response->json('delivery_status')) {
            $this->fail($response, self::label());
        }

        return $response->json('delivery_status');
    }

    public function mapStatus(string $raw): ShipmentStatus
    {
        return match (self::normalize($raw)) {
            'in_review' => ShipmentStatus::Booked,
            'pending' => ShipmentStatus::InTransit,
            'hold' => ShipmentStatus::Hold,
            'delivered', 'delivered_approval_pending' => ShipmentStatus::Delivered,
            'partial_delivered', 'partial_delivered_approval_pending' => ShipmentStatus::PartialDelivered,
            'cancelled', 'cancelled_approval_pending' => ShipmentStatus::Cancelled,
            default => ShipmentStatus::Unknown,
        };
    }

    public function parseWebhook(Request $request): ?WebhookUpdate
    {
        $this->assertToken($request->bearerToken());

        if ($request->input('notification_type') !== 'delivery_status' || ! $request->filled(['consignment_id', 'status'])) {
            return null;
        }

        return new WebhookUpdate((string) $request->input('consignment_id'), (string) $request->input('status'));
    }

    public function trackingUrl(Shipment $shipment): ?string
    {
        return $shipment->tracking_code ? "https://steadfast.com.bd/t/{$shipment->tracking_code}" : null;
    }

    private function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new CourierException('Steadfast-এর API key সেট করা নেই (সেটিংস → কুরিয়ার)।');
        }

        return Http::baseUrl(config('courier.steadfast.base_url'))
            ->timeout(20)
            ->acceptJson()
            ->withHeaders([
                'Api-Key' => $this->settings->steadfast_api_key,
                'Secret-Key' => $this->settings->steadfast_secret_key,
            ]);
    }
}

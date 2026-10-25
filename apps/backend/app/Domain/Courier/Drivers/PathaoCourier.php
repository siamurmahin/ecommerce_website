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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pathao merchant (aladdin) API: password-grant token, cached until it expires.
 * City/zone are left out so Pathao resolves them from the address.
 */
final class PathaoCourier implements Courier
{
    use CourierHelpers;

    public function __construct(private CourierSettings $settings) {}

    public static function label(): string
    {
        return 'Pathao';
    }

    public function isConfigured(): bool
    {
        $s = $this->settings;

        return filled($s->pathao_client_id) && filled($s->pathao_client_secret) && filled($s->pathao_username)
            && filled($s->pathao_password) && filled($s->pathao_store_id);
    }

    public function test(): string
    {
        $response = $this->http()->get('/aladdin/api/v1/stores');
        if (! $response->successful()) {
            $this->fail($response, self::label());
        }

        $store = collect($response->json('data.data') ?? [])->firstWhere('store_id', $this->settings->pathao_store_id);

        return $store ? 'স্টোর: '.$store['store_name'] : 'কানেক্টেড, কিন্তু স্টোর আইডি '.$this->settings->pathao_store_id.' পাওয়া যায়নি';
    }

    public function areaOptions(Order $order): ?array
    {
        return null;
    }

    public function book(Order $order, Parcel $parcel): Booking
    {
        $order->loadMissing('items');

        $response = $this->http()->post('/aladdin/api/v1/orders', [
            'store_id' => $this->settings->pathao_store_id,
            'merchant_order_id' => $order->number,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'recipient_address' => mb_substr($this->recipientAddress($order), 0, 220),
            'delivery_type' => 48, // normal delivery
            'item_type' => 2, // parcel
            'special_instruction' => $parcel->note,
            'item_quantity' => max(1, (int) $order->items->sum('quantity')),
            'item_weight' => max(0.5, $parcel->weightKg),
            'item_description' => mb_substr($this->itemDescription($order), 0, 250),
            'amount_to_collect' => $parcel->codTaka(),
        ]);

        $data = $response->json('data');
        if (! $response->successful() || ! isset($data['consignment_id'])) {
            $this->fail($response, self::label());
        }

        return new Booking(
            consignmentId: (string) $data['consignment_id'],
            trackingCode: (string) $data['consignment_id'],
            rawStatus: $data['order_status'] ?? 'Pending',
            deliveryFee: isset($data['delivery_fee']) ? (int) round($data['delivery_fee'] * 100) : null,
            payload: $data,
        );
    }

    public function fetchStatus(Shipment $shipment): string
    {
        $response = $this->http()->get("/aladdin/api/v1/orders/{$shipment->consignment_id}/info");
        $status = $response->json('data.order_status_slug') ?? $response->json('data.order_status');
        if (! $response->successful() || ! $status) {
            $this->fail($response, self::label());
        }

        return $status;
    }

    public function mapStatus(string $raw): ShipmentStatus
    {
        $status = self::normalize(preg_replace('/^order\./i', '', $raw));

        return match (true) {
            in_array($status, ['pending', 'pickup_requested', 'assigned_for_pickup', 'order_created', 'created'], true) => ShipmentStatus::Booked,
            in_array($status, ['delivered', 'paid'], true) => ShipmentStatus::Delivered,
            in_array($status, ['partial_delivery', 'partial_delivered'], true) => ShipmentStatus::PartialDelivered,
            in_array($status, ['return', 'returned', 'paid_return', 'pickup_cancelled', 'pickup_cancel'], true) => ShipmentStatus::Cancelled,
            in_array($status, ['on_hold', 'hold', 'delivery_failed', 'pickup_failed'], true) => ShipmentStatus::Hold,
            in_array($status, ['picked', 'in_transit', 'at_the_sorting_hub', 'at_sorting_hub', 'received_at_last_mile_hub', 'assigned_for_delivery', 'updated'], true) => ShipmentStatus::InTransit,
            default => ShipmentStatus::Unknown,
        };
    }

    public function parseWebhook(Request $request): ?WebhookUpdate
    {
        $this->assertToken($request->header('X-PATHAO-Signature'));

        // The setup ping has no consignment; it only checks our reply headers.
        if (! $request->filled('consignment_id') || ! $request->filled('event')) {
            return null;
        }

        return new WebhookUpdate((string) $request->input('consignment_id'), (string) $request->input('event'));
    }

    public function webhookResponse(): Response
    {
        return response()->json(['status' => 'success'], 202, [
            'X-Pathao-Merchant-Webhook-Integration-Secret' => config('courier.pathao.webhook_integration_secret'),
        ]);
    }

    public function trackingUrl(Shipment $shipment): ?string
    {
        $phone = $shipment->order?->customer_phone;

        return "https://merchant.pathao.com/tracking?consignment_id={$shipment->consignment_id}".($phone ? "&phone={$phone}" : '');
    }

    private function baseUrl(): string
    {
        return $this->settings->pathao_sandbox ? config('courier.pathao.sandbox_url') : config('courier.pathao.base_url');
    }

    private function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new CourierException('Pathao-র ক্রেডেনশিয়াল সেট করা নেই (সেটিংস → কুরিয়ার)।');
        }

        return Http::baseUrl($this->baseUrl())->timeout(20)->acceptJson()->withToken($this->token());
    }

    private function token(): string
    {
        $key = 'pathao_token:'.md5($this->baseUrl().$this->settings->pathao_client_id.$this->settings->pathao_username);

        if ($token = Cache::get($key)) {
            return $token;
        }

        $response = Http::baseUrl($this->baseUrl())->timeout(20)->acceptJson()->post('/aladdin/api/v1/issue-token', [
            'client_id' => $this->settings->pathao_client_id,
            'client_secret' => $this->settings->pathao_client_secret,
            'grant_type' => 'password',
            'username' => $this->settings->pathao_username,
            'password' => $this->settings->pathao_password,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            $this->fail($response, self::label());
        }

        $token = $response->json('access_token');
        Cache::put($key, $token, now()->addSeconds(max(60, (int) $response->json('expires_in', 3600) - 300)));

        return $token;
    }
}

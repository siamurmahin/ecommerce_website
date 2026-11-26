<?php

namespace App\Domain\Courier\Drivers;

use App\Domain\Courier\Contracts\BooksInBulk;
use App\Domain\Courier\Contracts\Courier;
use App\Domain\Courier\Contracts\PaysOut;
use App\Domain\Courier\Contracts\TakesReturns;
use App\Domain\Courier\CourierException;
use App\Domain\Courier\Data\Booking;
use App\Domain\Courier\Data\CourierReturn;
use App\Domain\Courier\Data\Parcel;
use App\Domain\Courier\Data\Payout;
use App\Domain\Courier\Data\PayoutLine;
use App\Domain\Courier\Data\WebhookUpdate;
use App\Domain\Courier\Drivers\Concerns\CourierHelpers;
use App\Domain\Courier\Enums\ShipmentStatus;
use App\Domain\Courier\Models\Shipment;
use App\Domain\Order\Models\Order;
use App\Settings\CourierSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Steadfast (packzy) merchant API: Api-Key + Secret-Key headers, webhook with a Bearer token.
 * Endpoints as in their Postman collection (documenter.getpostman.com/view/26211192/2sAYJ1mhpk).
 */
final class SteadfastCourier implements BooksInBulk, Courier, PaysOut, TakesReturns
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
        $response = $this->http()->post('/create_order', $this->orderPayload($order, $parcel));

        $consignment = $response->json('consignment');
        if (! $response->successful() || (int) $response->json('status') !== 200 || ! isset($consignment['consignment_id'])) {
            $this->fail($response, self::label());
        }

        return $this->booking($consignment);
    }

    public function bulkLimit(): int
    {
        return 500;
    }

    public function bookMany(array $parcels): array
    {
        $orders = collect($parcels)->mapWithKeys(fn (array $p) => [$p[0]->number => $p[0]]);

        // The body is the list of orders itself; every entry comes back with "status": "success" or an "error".
        $response = $this->http()->timeout(60)->post('/create_order/bulk-order', array_map(fn (array $p) => $this->orderPayload(...$p), $parcels));
        if (! $response->successful() || ! is_array($response->json('data'))) {
            $this->fail($response, self::label());
        }

        $results = [];
        foreach ($response->json('data') as $row) {
            $order = $orders->get($row['invoice'] ?? null);
            if (! $order) {
                continue;
            }
            $results[$order->id] = ($row['status'] ?? null) === 'success' && filled($row['consignment_id'] ?? null)
                ? $this->booking($row)
                : self::bulkError($row['error'] ?? null);
        }

        foreach ($orders as $order) {
            $results[$order->id] ??= 'Steadfast কোনো উত্তর দেয়নি';
        }

        return $results;
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

    public function payouts(): array
    {
        $response = $this->http()->get('/payments');
        if (! $response->successful() || ! is_array($response->json('payments'))) {
            $this->fail($response, self::label());
        }

        return collect($response->json('payments'))
            ->map(fn (array $p) => $this->payoutFrom($p))
            ->sortByDesc(fn (Payout $p) => $p->createdAt?->getTimestamp() ?? 0)
            ->values()->all();
    }

    public function payout(string $id): Payout
    {
        $response = $this->http()->get('/payments/'.rawurlencode($id));
        if (! $response->successful() || ! is_array($response->json('payment'))) {
            $this->fail($response, self::label());
        }

        return $this->payoutFrom($response->json('payment'));
    }

    public function requestReturn(Shipment $shipment, string $reason): CourierReturn
    {
        $response = $this->http()->post('/create_return_request', [
            'consignment_id' => $shipment->consignment_id,
            'reason' => mb_substr($reason, 0, 250),
        ]);
        if (! $response->successful() || ! $response->json('id')) {
            $this->fail($response, self::label());
        }

        return new CourierReturn((string) $response->json('id'), (string) $response->json('status', 'pending'));
    }

    public function returnStatus(string $id): CourierReturn
    {
        $response = $this->http()->get('/get_return_request/'.rawurlencode($id));
        if (! $response->successful() || ! $response->json('data.id')) {
            $this->fail($response, self::label());
        }

        return new CourierReturn((string) $response->json('data.id'), (string) $response->json('data.status'));
    }

    /** @return array<string, mixed> */
    private function orderPayload(Order $order, Parcel $parcel): array
    {
        return [
            'invoice' => $order->number,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'recipient_address' => mb_substr($this->recipientAddress($order), 0, 250),
            'cod_amount' => $parcel->codTaka(),
            'note' => $parcel->note,
            'item_description' => mb_substr($this->itemDescription($order), 0, 250),
        ];
    }

    /** @param  array<string, mixed>  $consignment */
    private function booking(array $consignment): Booking
    {
        return new Booking(
            consignmentId: (string) $consignment['consignment_id'],
            trackingCode: $consignment['tracking_code'] ?? null,
            rawStatus: $consignment['status'] ?? 'in_review',
            payload: $consignment,
        );
    }

    /** Bulk errors arrive as a JSON-encoded list of codes: "[\"THIS_INVOICE_ALREADY_EXISTS\"]". */
    private static function bulkError(mixed $error): string
    {
        $codes = is_string($error) ? (json_decode($error, true) ?? [$error]) : (array) $error;
        $message = collect($codes)->flatten()->filter()->map(fn ($c) => str_replace('_', ' ', strtolower((string) $c)))->implode(', ');

        return 'Steadfast: '.($message ?: 'বুক হয়নি');
    }

    /** @param  array<string, mixed>  $p  amounts in taka */
    private function payoutFrom(array $p): Payout
    {
        $poisha = fn ($v) => (int) round((float) $v * 100);
        $date = fn ($v) => filled($v) ? CarbonImmutable::parse($v, config('shop.timezone'))->utc() : null;

        return new Payout(
            id: (string) $p['payment_id'],
            amount: $poisha($p['amount'] ?? 0),
            charges: $poisha($p['charges'] ?? 0),
            total: $poisha($p['total'] ?? 0),
            method: $p['method'] ?? null,
            status: (string) ($p['status_label'] ?? ''),
            createdAt: $date($p['created_at'] ?? null),
            paidAt: $date($p['paid_at'] ?? null),
            lines: collect($p['consignments'] ?? [])->map(fn (array $c) => new PayoutLine(
                consignmentId: (string) $c['consignment_id'],
                invoice: $c['invoice'] ?? null,
                codAmount: $poisha($c['cod_amount'] ?? 0),
                rawStatus: (string) ($c['status'] ?? ''),
            ))->all(),
        );
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

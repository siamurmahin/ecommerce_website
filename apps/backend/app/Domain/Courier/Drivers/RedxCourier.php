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
use Illuminate\Support\Str;

/**
 * RedX open API. Parcels need RedX's own delivery-area id, so the booking dialog offers the
 * areas of the order's district with the thana pre-matched. Webhook auth is ?token= on the URL.
 */
final class RedxCourier implements Courier
{
    use CourierHelpers;

    public function __construct(private CourierSettings $settings) {}

    public static function label(): string
    {
        return 'RedX';
    }

    public function isConfigured(): bool
    {
        return filled($this->settings->redx_token);
    }

    public function test(): string
    {
        return 'কানেক্টেড — ঢাকায় '.count($this->areas('Dhaka')).'টা এরিয়া';
    }

    public function areaOptions(Order $order): ?array
    {
        $district = $order->district?->name_en;

        return $district ? collect($this->areas($district))->pluck('name', 'id')->all() : [];
    }

    /** Best guess for the order's area: the thana's name, else the district's own name. */
    public function guessArea(Order $order): ?int
    {
        $areas = collect($this->areas((string) $order->district?->name_en));
        $thana = Str::lower((string) $order->thana?->name_en);

        $match = ($thana ? $areas->first(fn ($a) => Str::lower($a['name']) === $thana)
            ?? $areas->first(fn ($a) => Str::contains(Str::lower($a['name']), $thana)) : null)
            ?? $areas->first(fn ($a) => Str::lower($a['name']) === Str::lower((string) $order->district?->name_en));

        return $match['id'] ?? null;
    }

    public function book(Order $order, Parcel $parcel): Booking
    {
        $areaId = $parcel->areaId ?: $this->guessArea($order);
        $area = collect($this->areas((string) $order->district?->name_en))->firstWhere('id', (int) $areaId);

        if (! $area) {
            throw new CourierException('RedX: ডেলিভারি এরিয়া বেছে নিন।');
        }

        $response = $this->http()->post('/parcel', array_filter([
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'delivery_area' => $area['name'],
            'delivery_area_id' => (int) $area['id'],
            'customer_address' => mb_substr($this->recipientAddress($order), 0, 250),
            'merchant_invoice_id' => $order->number,
            'cash_collection_amount' => (string) $parcel->codTaka(),
            'parcel_weight' => (int) round(max(0.5, $parcel->weightKg) * 1000), // grams
            'instruction' => $parcel->note,
            'value' => (int) round($order->subtotal / 100),
            'pickup_store_id' => $this->settings->redx_pickup_store_id,
        ], fn ($v) => $v !== null && $v !== ''));

        if (! $response->successful() || ! $response->json('tracking_id')) {
            $this->fail($response, self::label());
        }

        return new Booking(
            consignmentId: (string) $response->json('tracking_id'),
            trackingCode: (string) $response->json('tracking_id'),
            rawStatus: 'pickup-pending',
            payload: $response->json() + ['delivery_area_id' => (int) $area['id']],
        );
    }

    public function fetchStatus(Shipment $shipment): string
    {
        $response = $this->http()->get("/parcel/info/{$shipment->consignment_id}");
        if (! $response->successful() || ! $response->json('parcel.status')) {
            $this->fail($response, self::label());
        }

        return $response->json('parcel.status');
    }

    public function mapStatus(string $raw): ShipmentStatus
    {
        $status = self::normalize($raw);

        return match (true) {
            in_array($status, ['pickup_pending', 'ready_for_pickup'], true) => ShipmentStatus::Booked,
            $status === 'delivered' => ShipmentStatus::Delivered,
            in_array($status, ['partial_delivered', 'partially_delivered'], true) => ShipmentStatus::PartialDelivered,
            in_array($status, ['returned', 'cancelled', 'cancel', 'rejected'], true) => ShipmentStatus::Cancelled,
            str_contains($status, 'hold') => ShipmentStatus::Hold,
            in_array($status, ['pickup_in_progress', 'picked_up', 'ready_for_delivery', 'delivery_in_progress', 'in_transit', 'agent_returning', 'agent_area_change', 'agent_reassigned'], true) => ShipmentStatus::InTransit,
            default => ShipmentStatus::Unknown,
        };
    }

    public function parseWebhook(Request $request): ?WebhookUpdate
    {
        $this->assertToken($request->query('token'));

        if (! $request->filled(['tracking_number', 'status'])) {
            return null;
        }

        return new WebhookUpdate((string) $request->input('tracking_number'), (string) $request->input('status'));
    }

    public function trackingUrl(Shipment $shipment): ?string
    {
        return "https://redx.com.bd/track-parcel/?trackingId={$shipment->consignment_id}";
    }

    /** @return list<array{id:int, name:string}> RedX areas for a district, cached for a day */
    public function areas(string $district): array
    {
        if ($district === '') {
            return [];
        }

        return Cache::remember('redx_areas:'.md5($this->baseUrl().$district), now()->addDay(), function () use ($district) {
            $response = $this->http()->get('/areas', ['district_name' => $district]);
            if (! $response->successful()) {
                $this->fail($response, self::label());
            }

            return collect($response->json('areas') ?? [])
                ->map(fn ($a) => ['id' => (int) $a['id'], 'name' => (string) $a['name']])
                ->sortBy('name')->values()->all();
        });
    }

    private function baseUrl(): string
    {
        return $this->settings->redx_sandbox ? config('courier.redx.sandbox_url') : config('courier.redx.base_url');
    }

    private function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new CourierException('RedX-এর API token সেট করা নেই (সেটিংস → কুরিয়ার)।');
        }

        return Http::baseUrl($this->baseUrl())->timeout(20)->acceptJson()
            ->withHeaders(['API-ACCESS-TOKEN' => 'Bearer '.$this->settings->redx_token]);
    }
}

<?php

namespace App\Domain\Tracking\Drivers;

use App\Domain\Tracking\Contracts\ServerEventDriver;
use App\Domain\Tracking\ServerEvent;
use App\Settings\TrackingSettings;
use Illuminate\Support\Facades\Http;

/**
 * GA4 Measurement Protocol, for the purchase only: GA4 drops a second purchase with the same
 * `transaction_id`, so the server copy just fills in orders whose browser tag was blocked.
 * Other GA4 events have no de-duplication and stay browser-only.
 */
final class Ga4MeasurementProtocol implements ServerEventDriver
{
    private const NAMES = [ServerEvent::PURCHASE => 'purchase'];

    public function __construct(private TrackingSettings $settings) {}

    public function enabled(): bool
    {
        return $this->settings->ga4ServerEnabled();
    }

    public function supports(string $event): bool
    {
        return isset(self::NAMES[$event]);
    }

    public function send(ServerEvent $event): void
    {
        Http::timeout(8)
            ->withQueryParameters([
                'measurement_id' => $this->settings->ga4_measurement_id,
                'api_secret' => $this->settings->ga4_api_secret,
            ])
            ->post('https://www.google-analytics.com/mp/collect', $this->payload($event))
            ->throw();
    }

    /** @return array<string, mixed> */
    public function payload(ServerEvent $event): array
    {
        // Without the browser's _ga id the hit still counts, just as a separate visitor.
        $clientId = $event->user['ga_client_id'] ?? null
            ?: sprintf('%u.%d', crc32($event->user['phone'] ?? $event->eventId), $event->time);

        return [
            'client_id' => $clientId,
            'timestamp_micros' => $event->time * 1_000_000,
            'events' => [[
                'name' => self::NAMES[$event->name],
                'params' => array_filter([
                    'transaction_id' => $event->orderNumber ?? $event->eventId,
                    'currency' => $event->currency,
                    'value' => $event->value / 100,
                    'shipping' => $event->shipping / 100,
                    'session_id' => $event->user['ga_session_id'] ?? null,
                    'engagement_time_msec' => 1,
                    'items' => array_map(fn (array $item) => array_filter([
                        'item_id' => $item['id'],
                        'item_name' => $item['name'],
                        'item_category' => $item['category'],
                        'price' => $item['price'] / 100,
                        'quantity' => $item['quantity'],
                    ], fn ($v) => $v !== null), $event->items),
                ], fn ($v) => $v !== null),
            ]],
        ];
    }
}

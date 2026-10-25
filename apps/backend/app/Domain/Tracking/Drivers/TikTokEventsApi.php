<?php

namespace App\Domain\Tracking\Drivers;

use App\Domain\Tracking\Contracts\ServerEventDriver;
use App\Domain\Tracking\ServerEvent;
use App\Settings\TrackingSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** TikTok Events API (v1.3). De-duplicated against the browser pixel by `event_id` + event name. */
final class TikTokEventsApi implements ServerEventDriver
{
    /** Meta-style names → TikTok's standard events. */
    public const EVENTS = [
        ServerEvent::VIEW_CONTENT => 'ViewContent',
        ServerEvent::ADD_TO_CART => 'AddToCart',
        ServerEvent::INITIATE_CHECKOUT => 'InitiateCheckout',
        ServerEvent::PURCHASE => 'CompletePayment',
    ];

    public function __construct(private TrackingSettings $settings) {}

    public function enabled(): bool
    {
        return $this->settings->tiktokServerEnabled();
    }

    public function supports(string $event): bool
    {
        return isset(self::EVENTS[$event]);
    }

    public function send(ServerEvent $event): void
    {
        $response = Http::timeout(8)
            ->withHeaders(['Access-Token' => $this->settings->tiktok_access_token])
            ->post(config('tracking.tiktok_api_url'), array_filter([
                'event_source' => 'web',
                'event_source_id' => $this->settings->tiktok_pixel_id,
                'test_event_code' => $this->settings->tiktok_test_event_code,
                'data' => [$this->payload($event)],
            ]))
            ->throw();

        // TikTok answers HTTP 200 with a non-zero `code` for rejected events.
        if ((int) $response->json('code', 0) !== 0) {
            throw new RuntimeException('TikTok Events API: '.$response->json('message'));
        }
    }

    /** @return array<string, mixed> */
    public function payload(ServerEvent $event): array
    {
        $u = $event->user;
        $hash = fn (?string $value) => filled($value) ? hash('sha256', Str::lower(trim($value))) : null;
        $phone = filled($u['phone'] ?? null) ? '+88'.$u['phone'] : null; // E.164: +8801XXXXXXXXX

        return array_filter([
            'event' => self::EVENTS[$event->name],
            'event_time' => $event->time,
            'event_id' => $event->eventId,
            'user' => array_filter([
                'phone' => $hash($phone),
                'email' => $hash($u['email'] ?? null),
                'external_id' => $hash($phone),
                'ip' => $u['ip'] ?? null,
                'user_agent' => $u['user_agent'] ?? null,
                'ttp' => $u['ttp'] ?? null,
                'ttclid' => $u['ttclid'] ?? null,
            ]),
            'page' => $event->sourceUrl ? ['url' => $event->sourceUrl] : null,
            'properties' => array_filter([
                'currency' => $event->currency,
                'value' => $event->value / 100,
                'content_type' => 'product',
                'contents' => array_map(fn (array $item) => [
                    'content_id' => $item['id'],
                    'content_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'] / 100,
                ], $event->items),
                'order_id' => $event->orderNumber,
            ], fn ($v) => $v !== null),
        ], fn ($v) => $v !== null && $v !== []);
    }
}

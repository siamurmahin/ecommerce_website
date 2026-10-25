<?php

namespace App\Domain\Tracking\Drivers;

use App\Domain\Tracking\Contracts\ServerEventDriver;
use App\Domain\Tracking\ServerEvent;
use App\Settings\TrackingSettings;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Meta Conversions API. De-duplicated against the browser pixel by `event_id` + event name. */
final class MetaConversionsApi implements ServerEventDriver
{
    public function __construct(private TrackingSettings $settings) {}

    public function enabled(): bool
    {
        return $this->settings->metaServerEnabled();
    }

    public function supports(string $event): bool
    {
        return in_array($event, [ServerEvent::VIEW_CONTENT, ServerEvent::ADD_TO_CART, ServerEvent::INITIATE_CHECKOUT, ServerEvent::PURCHASE], true);
    }

    public function send(ServerEvent $event): void
    {
        $this->post([$this->payload($event)]);
    }

    /**
     * Checks the pixel ID and token against the Graph API; with a test event code it also sends
     * a PageView that shows up under Events Manager → Test events. Returns the pixel's name.
     *
     * @throws RequestException
     */
    public function testConnection(): string
    {
        $pixel = $this->http()
            ->get($this->url((string) $this->settings->meta_pixel_id), ['fields' => 'id,name'])
            ->throw()
            ->json();

        if (filled($this->settings->meta_test_event_code)) {
            $this->post([[
                'event_name' => 'PageView',
                'event_time' => now()->getTimestamp(),
                'event_id' => 'test-'.Str::uuid(),
                'action_source' => 'website',
                'event_source_url' => config('shop.storefront_url'),
                'user_data' => ['client_ip_address' => request()->ip(), 'client_user_agent' => (string) request()->userAgent()],
            ]]);
        }

        return $pixel['name'] ?? $pixel['id'] ?? '';
    }

    /** @return array<string, mixed> */
    public function payload(ServerEvent $event): array
    {
        $u = $event->user;
        $hash = fn (?string $value) => filled($value) ? hash('sha256', Str::lower(trim($value))) : null;
        $phone = filled($u['phone'] ?? null) ? '88'.$u['phone'] : null; // 01XXXXXXXXX → 8801XXXXXXXXX
        $firstName = filled($u['name'] ?? null) ? Str::before(trim($u['name']), ' ') : null;

        $userData = array_filter([
            'ph' => $phone ? [$hash($phone)] : null,
            'em' => filled($u['email'] ?? null) ? [$hash($u['email'])] : null,
            'fn' => $firstName ? [$hash($firstName)] : null,
            'external_id' => $phone ? [$hash($phone)] : null,
            'country' => [$hash('bd')],
            'client_ip_address' => $u['ip'] ?? null,
            'client_user_agent' => $u['user_agent'] ?? null,
            'fbp' => $u['fbp'] ?? null,
            'fbc' => $u['fbc'] ?? null,
        ]);

        return array_filter([
            'event_name' => $event->name,
            'event_time' => $event->time,
            'event_id' => $event->eventId,
            'action_source' => 'website',
            'event_source_url' => $event->sourceUrl,
            'user_data' => $userData,
            'custom_data' => array_filter([
                'currency' => $event->currency,
                'value' => $event->value / 100,
                'content_type' => 'product',
                'content_ids' => $event->contentIds(),
                'contents' => array_map(fn (array $item) => [
                    'id' => $item['id'],
                    'quantity' => $item['quantity'],
                    'item_price' => $item['price'] / 100,
                ], $event->items),
                'num_items' => $event->numItems(),
                'order_id' => $event->orderNumber,
            ], fn ($v) => $v !== null),
        ], fn ($v) => $v !== null);
    }

    private function post(array $events): void
    {
        $this->http()
            ->post($this->url($this->settings->meta_pixel_id.'/events'), array_filter([
                'data' => $events,
                'test_event_code' => $this->settings->meta_test_event_code,
            ]))
            ->throw();
    }

    private function http()
    {
        return Http::timeout(8)->withQueryParameters(['access_token' => $this->settings->meta_capi_token]);
    }

    private function url(string $path): string
    {
        return 'https://graph.facebook.com/'.config('tracking.meta_graph_version').'/'.$path;
    }
}

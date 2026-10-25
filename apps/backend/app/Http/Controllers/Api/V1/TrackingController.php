<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Order\Support\CartPricer;
use App\Domain\Tracking\ServerEvent;
use App\Domain\Tracking\ServerTracker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TrackEventRequest;
use App\Settings\TrackingSettings;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    /**
     * Public pixel IDs and custom scripts for the storefront. Server secrets are never included.
     *
     * @response array{
     *   gtm_id: string|null,
     *   ga4_measurement_id: string|null,
     *   meta_pixel_id: string|null,
     *   tiktok_pixel_id: string|null,
     *   google_ads_id: string|null,
     *   google_ads_purchase_label: string|null,
     *   clarity_id: string|null,
     *   gsc_verification: string|null,
     *   fb_domain_verification: string|null,
     *   server_events: bool,
     *   scripts: array{head: string|null, body_start: string|null, body_end: string|null},
     *   page_scripts: list<array{id: string, paths: list<string>, placement: string, code: string}>
     * }
     */
    public function config(TrackingSettings $settings, ServerTracker $tracker): array
    {
        return [
            'gtm_id' => $settings->gtm_id,
            'ga4_measurement_id' => $settings->ga4_measurement_id,
            'meta_pixel_id' => $settings->meta_pixel_id,
            'tiktok_pixel_id' => $settings->tiktok_pixel_id,
            'google_ads_id' => $settings->google_ads_id,
            'google_ads_purchase_label' => $settings->google_ads_purchase_label,
            'clarity_id' => $settings->clarity_id,
            'gsc_verification' => $settings->gsc_verification,
            'fb_domain_verification' => $settings->fb_domain_verification,
            'server_events' => collect(ServerEvent::BROWSER_REPORTED)->contains(fn (string $e) => $tracker->wants($e)),
            'scripts' => [
                'head' => $settings->head_scripts,
                'body_start' => $settings->body_start_scripts,
                'body_end' => $settings->body_end_scripts,
            ],
            'page_scripts' => collect($settings->page_scripts)
                ->filter(fn (array $s) => ($s['enabled'] ?? true) && filled($s['code'] ?? null) && filled($s['paths'] ?? null))
                ->map(fn (array $s, int $i) => [
                    'id' => 'ps-'.$i.'-'.substr(md5($s['code']), 0, 8),
                    'paths' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $s['paths'])))),
                    'placement' => ($s['placement'] ?? 'body_end') === 'head' ? 'head' : 'body_end',
                    'code' => (string) $s['code'],
                ])->values()->all(),
        ];
    }

    /** Server copy of a browser event (ViewContent, AddToCart, InitiateCheckout), same `event_id`. */
    public function event(TrackEventRequest $request, CartPricer $pricer, ServerTracker $tracker): Response
    {
        if ($tracker->wants($request->input('name'))) {
            $tracker->dispatch(ServerEvent::fromQuote(
                $request->input('name'),
                $request->input('event_id'),
                $pricer->quote($request->input('items')),
                [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'fbp' => $request->input('fbp'),
                    'fbc' => $request->input('fbc'),
                    'ttp' => $request->input('ttp'),
                    'ttclid' => $request->input('ttclid'),
                ],
                $request->input('url'),
            ));
        }

        return response()->noContent(202);
    }
}

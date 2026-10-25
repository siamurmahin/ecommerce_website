<?php

namespace App\Domain\Courier\Drivers\Concerns;

use App\Domain\Courier\CourierException;
use App\Domain\Order\Models\Order;
use App\Settings\CourierSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

trait CourierHelpers
{
    protected function recipientAddress(Order $order): string
    {
        return collect([$order->address, $order->thana_name, $order->district_name])->filter()->unique()->implode(', ');
    }

    protected function itemDescription(Order $order): string
    {
        return $order->loadMissing('items')->items
            ->map(fn ($item) => $item->name.($item->quantity > 1 ? " ×{$item->quantity}" : ''))
            ->implode(', ');
    }

    /** @throws AuthorizationException */
    protected function assertToken(?string $given): void
    {
        $expected = app(CourierSettings::class)->webhook_token;

        if (blank($given) || ! hash_equals($expected, $given)) {
            throw new AuthorizationException('Invalid webhook token');
        }
    }

    /** First readable error from a courier response: "message", then field errors. */
    protected function fail(HttpResponse $response, string $courier): never
    {
        $errors = collect($response->json('errors') ?? [])->flatten()->filter()->implode(' ');
        $message = trim(($response->json('message') ?? '').' '.$errors) ?: "HTTP {$response->status()}";

        throw new CourierException("{$courier}: {$message}");
    }

    public function webhookResponse(): Response
    {
        return response()->json(['status' => 'success']);
    }

    protected static function normalize(string $raw): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim($raw)));
    }
}

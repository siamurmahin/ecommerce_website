<?php

namespace App\Domain\Tracking;

use App\Domain\Order\Models\Order;
use App\Domain\Order\Support\Quote;
use App\Domain\Order\Support\QuoteLine;

/**
 * One standard ecommerce event, sent server-side with the same `event_id` the browser pixel used.
 * Names follow Meta's standard events; drivers map them to their own. Money is integer poisha.
 */
final class ServerEvent
{
    public const VIEW_CONTENT = 'ViewContent';

    public const ADD_TO_CART = 'AddToCart';

    public const INITIATE_CHECKOUT = 'InitiateCheckout';

    public const PURCHASE = 'Purchase';

    /** Events the storefront may report; Purchase only ever comes from a placed order. */
    public const BROWSER_REPORTED = [self::VIEW_CONTENT, self::ADD_TO_CART, self::INITIATE_CHECKOUT];

    /**
     * @param  list<array{id:string, name:string, price:int, quantity:int, category:string|null}>  $items
     * @param  array{phone?:string|null, email?:string|null, name?:string|null, ip?:string|null, user_agent?:string|null, fbp?:string|null, fbc?:string|null, ttp?:string|null, ttclid?:string|null, ga_client_id?:string|null, ga_session_id?:string|null}  $user
     */
    public function __construct(
        public readonly string $name,
        public readonly string $eventId,
        public readonly int $time,
        public readonly int $value,
        public readonly array $items,
        public readonly array $user = [],
        public readonly ?string $sourceUrl = null,
        public readonly ?string $orderNumber = null,
        public readonly int $shipping = 0,
        public readonly string $currency = 'BDT',
    ) {}

    public static function purchase(Order $order): self
    {
        $order->loadMissing('items.product.category');

        return new self(
            name: self::PURCHASE,
            eventId: $order->event_id ?: $order->number,
            time: $order->created_at->getTimestamp(),
            value: $order->total,
            items: $order->items->map(fn ($item) => [
                'id' => (string) $item->product_id,
                'name' => $item->product?->name_en ?: $item->name,
                'price' => $item->unit_price,
                'quantity' => $item->quantity,
                'category' => $item->product?->category?->name_en ?: $item->product?->category?->name,
            ])->values()->all(),
            user: [
                'phone' => $order->customer_phone,
                'email' => $order->customer_email,
                'name' => $order->customer_name,
                'ip' => $order->ip,
                'user_agent' => $order->user_agent,
                'fbp' => $order->fbp,
                'fbc' => $order->fbc,
                'ttp' => $order->ttp,
                'ttclid' => $order->ttclid,
                'ga_client_id' => $order->ga_client_id,
                'ga_session_id' => $order->ga_session_id,
            ],
            sourceUrl: rtrim(config('shop.storefront_url'), '/').'/checkout',
            orderNumber: $order->number,
            shipping: $order->delivery_charge,
        );
    }

    /** Browser-reported event, priced from the database (the storefront's numbers aren't trusted). */
    public static function fromQuote(string $name, string $eventId, Quote $quote, array $user, ?string $sourceUrl): self
    {
        $lines = array_values(array_filter($quote->lines, fn (QuoteLine $l) => $l->product !== null));

        return new self(
            name: $name,
            eventId: $eventId,
            time: now()->getTimestamp(),
            value: array_sum(array_map(fn (QuoteLine $l) => $l->unitPrice * $l->quantity, $lines)),
            items: array_map(fn (QuoteLine $l) => [
                'id' => (string) $l->productId,
                'name' => $l->product->name_en ?: $l->product->name,
                'price' => $l->unitPrice,
                'quantity' => $l->quantity,
                'category' => $l->product->category?->name_en ?: $l->product->category?->name,
            ], $lines),
            user: $user,
            sourceUrl: $sourceUrl,
        );
    }

    public function contentIds(): array
    {
        return array_column($this->items, 'id');
    }

    public function numItems(): int
    {
        return array_sum(array_column($this->items, 'quantity'));
    }
}

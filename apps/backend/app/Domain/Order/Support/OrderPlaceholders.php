<?php

namespace App\Domain\Order\Support;

use App\Domain\Order\Models\Order;
use App\Settings\BrandSettings;
use App\Settings\SiteSettings;

/** The {name}, {order}, {total}… values order messages (SMS, WhatsApp, email) fill in. */
final class OrderPlaceholders
{
    /** @return array<string, string> placeholder => value */
    public static function for(Order $order): array
    {
        return [
            '{name}' => $order->customer_name,
            '{order}' => (string) $order->number,
            '{total}' => '৳'.number_format($order->total / 100, $order->total % 100 ? 2 : 0),
            '{phone}' => $order->customer_phone,
            '{shop}' => app(BrandSettings::class)->name,
            '{hotline}' => app(SiteSettings::class)->hotline,
            '{track_url}' => rtrim((string) config('shop.storefront_url'), '/').'/track-order',
        ];
    }
}

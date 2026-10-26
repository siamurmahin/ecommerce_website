<?php

namespace App\Support;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Returns\Models\OrderReturn;
use App\Settings\ReturnSettings;
use Carbon\CarbonImmutable;

/** Whether a customer may still ask to return or exchange an order. */
final class Returns
{
    public static function deliveredAt(Order $order): ?CarbonImmutable
    {
        $history = $order->relationLoaded('statusHistories')
            ? $order->statusHistories->where('to', OrderStatus::Delivered)->sortByDesc('id')->first()
            : $order->statusHistories()->where('to', OrderStatus::Delivered->value)->latest('id')->first();

        return $history ? CarbonImmutable::parse($history->created_at) : null;
    }

    public static function accepts(Order $order): bool
    {
        $settings = app(ReturnSettings::class);
        $delivered = self::deliveredAt($order);

        return $settings->enabled
            && $order->status === OrderStatus::Delivered
            && $delivered !== null
            && $delivered->addDays($settings->window_days)->isFuture()
            && ! OrderReturn::where('order_id', $order->id)->open()->exists();
    }
}

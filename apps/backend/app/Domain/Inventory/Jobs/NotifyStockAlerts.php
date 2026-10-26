<?php

namespace App\Domain\Inventory\Jobs;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockAlert;
use App\Domain\Sms\SmsSender;
use App\Settings\BrandSettings;
use App\Settings\SmsSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A product (or variant) is back in stock: text everyone waiting for it. With SMS off the requests stay
 * pending, so staff can see them under ক্যাটালগ → স্টকে এলে জানান and call instead.
 */
class NotifyStockAlerts implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $productId, public ?int $variantId = null) {}

    public function handle(SmsSettings $settings, SmsSender $sms): void
    {
        $product = Product::find($this->productId);
        if (! $product || ! $settings->enabled || ! $settings->stock_alert_enabled || blank($settings->stock_alert_template)) {
            return;
        }

        $message = strtr($settings->stock_alert_template, [
            '{product}' => $product->name,
            '{link}' => rtrim((string) config('shop.storefront_url'), '/').'/p/'.$product->slug,
            '{shop}' => app(BrandSettings::class)->name,
        ]);

        StockAlert::query()->pending()->where('product_id', $product->id)
            // A product-level request is answered by any variant coming back.
            ->when($this->variantId, fn ($q) => $q->where(fn ($q) => $q->where('product_variant_id', $this->variantId)->orWhereNull('product_variant_id')))
            ->each(function (StockAlert $alert) use ($sms, $message) {
                $sms->queue($alert->phone, $message, 'stock_alert');
                $alert->update(['notified_at' => now()]);
            });
    }
}

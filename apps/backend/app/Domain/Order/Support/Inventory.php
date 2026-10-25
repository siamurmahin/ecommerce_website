<?php

namespace App\Domain\Order\Support;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\StockReason;
use App\Domain\Inventory\StockLedger;
use App\Domain\Order\Models\Order;
use App\Support\StorefrontCache;
use Illuminate\Validation\ValidationException;

/** Moves an order's quantities out of (or back into) stock. Call inside a transaction. */
final class Inventory
{
    public function deduct(Order $order): void
    {
        StockLedger::within(StockReason::Sale, fn () => $this->take($order), orderId: $order->id, note: $order->number);
        $this->refreshStorefront($order);
    }

    public function restock(Order $order): void
    {
        StockLedger::within(StockReason::Restock, fn () => $this->putBack($order), orderId: $order->id, note: $order->number);
        $this->refreshStorefront($order);
    }

    private function take(Order $order): void
    {
        foreach ($order->items as $item) {
            $row = $item->product_variant_id
                ? ProductVariant::lockForUpdate()->find($item->product_variant_id)
                : Product::lockForUpdate()->find($item->product_id);

            if ($row && $row->stock < $item->quantity) {
                throw ValidationException::withMessages([
                    'items' => "\"{$item->name}\" এর পর্যাপ্ত স্টক নেই।",
                ]);
            }

            $row?->decrement('stock', $item->quantity);
            Product::whereKey($item->product_id)->increment('sold_count', $item->quantity);
        }
    }

    private function putBack(Order $order): void
    {
        foreach ($order->items as $item) {
            $row = $item->product_variant_id
                ? ProductVariant::find($item->product_variant_id)
                : Product::find($item->product_id);

            $row?->increment('stock', $item->quantity);
            Product::whereKey($item->product_id)->where('sold_count', '>=', $item->quantity)->decrement('sold_count', $item->quantity);
        }
    }

    /** Stock and sold counts show on product pages and cards; increments skip model events, so say so here. */
    private function refreshStorefront(Order $order): void
    {
        $slugs = Product::withTrashed()->whereKey($order->items->pluck('product_id')->filter())->pluck('slug');

        StorefrontCache::revalidate('products', 'home', ...$slugs->map(fn (string $slug) => "product:{$slug}"));
    }
}

<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Enums\StockReason;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\StockLedger;
use App\Support\StorefrontCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The goods arrived: add every line to stock and note the latest cost price. */
final class ReceivePurchaseOrder
{
    public function handle(PurchaseOrder $po): void
    {
        if (! $po->status->editable()) {
            throw ValidationException::withMessages(['status' => 'এই পারচেজ অর্ডার আগেই গ্রহণ বা বাতিল হয়েছে।']);
        }
        if (! $po->items()->exists()) {
            throw ValidationException::withMessages(['items' => 'আগে পণ্য যোগ করুন।']);
        }

        DB::transaction(fn () => StockLedger::within(StockReason::Purchase, function () use ($po) {
            foreach ($po->items()->with('product')->get() as $item) {
                $row = $item->product_variant_id
                    ? ProductVariant::lockForUpdate()->find($item->product_variant_id)
                    : Product::lockForUpdate()->find($item->product_id);
                $row?->increment('stock', $item->quantity);

                if ($item->unit_cost > 0) {
                    // A variant keeps its own purchase price; a plain product's goes on the product.
                    $item->product_variant_id
                        ? ProductVariant::whereKey($item->product_variant_id)->update(['cost_price' => $item->unit_cost])
                        : Product::whereKey($item->product_id)->update(['cost_price' => $item->unit_cost]);
                }
            }

            $po->update(['status' => PurchaseStatus::Received, 'received_at' => now()]);
        }, purchaseOrderId: $po->id, note: $po->number));

        $slugs = Product::whereKey($po->items()->pluck('product_id'))->pluck('slug');
        StorefrontCache::revalidate('products', 'home', ...$slugs->map(fn (string $s) => "product:{$s}"));
    }
}

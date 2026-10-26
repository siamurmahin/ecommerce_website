<?php

namespace App\Domain\Inventory;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\StockReason;
use App\Domain\Inventory\Jobs\NotifyStockAlerts;
use App\Domain\Inventory\Models\StockMovement;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Records every stock change. Product and variant models report changes to `stock` here (see their
 * `updated`/`created` hooks); code that moves stock for a reason wraps the change in `within()`,
 * anything else (an edit in admin) is logged as a manual adjustment by the signed-in user.
 */
final class StockLedger
{
    /** @var list<array{reason: StockReason, order_id?: int|null, purchase_order_id?: int|null, note?: string|null}> */
    private static array $context = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function within(StockReason $reason, Closure $callback, ?int $orderId = null, ?int $purchaseOrderId = null, ?string $note = null): mixed
    {
        self::$context[] = ['reason' => $reason, 'order_id' => $orderId, 'purchase_order_id' => $purchaseOrderId, 'note' => $note];

        try {
            return $callback();
        } finally {
            array_pop(self::$context);
        }
    }

    public static function record(Model $row, int $before): void
    {
        $after = (int) $row->stock;
        if ($after === $before) {
            return;
        }

        $context = end(self::$context) ?: ['reason' => StockReason::Adjustment];
        StockMovement::create([
            'product_id' => $row instanceof ProductVariant ? $row->product_id : $row->getKey(),
            'product_variant_id' => $row instanceof ProductVariant ? $row->getKey() : null,
            'change' => $after - $before,
            'balance' => $after,
            'reason' => $context['reason'],
            'order_id' => $context['order_id'] ?? null,
            'purchase_order_id' => $context['purchase_order_id'] ?? null,
            'note' => $context['note'] ?? null,
            'user_id' => auth()->user() instanceof User ? auth()->id() : null, // staff only, not a signed-in customer
        ]);
    }

    /** Model hooks for Product and ProductVariant. */
    public static function observe(string $model): void
    {
        /** @var class-string<Product|ProductVariant> $model */
        $model::created(fn (Model $row) => (int) $row->stock !== 0 ? self::record($row, 0) : null);
        $model::updated(function (Model $row) {
            if ($row->wasChanged('stock')) {
                self::record($row, (int) $row->getOriginal('stock'));
                // Back from zero: tell the customers waiting for it.
                if ((int) $row->getOriginal('stock') <= 0 && (int) $row->stock > 0) {
                    $row instanceof ProductVariant
                        ? NotifyStockAlerts::dispatch($row->product_id, $row->getKey())->afterCommit()
                        : NotifyStockAlerts::dispatch($row->getKey())->afterCommit();
                }
            }
        });
    }
}

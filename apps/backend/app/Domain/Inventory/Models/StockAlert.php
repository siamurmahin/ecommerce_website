<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer asked to be told when an out-of-stock product (or one variant of it) is back. */
#[Fillable(['product_id', 'product_variant_id', 'phone', 'notified_at'])]
class StockAlert extends Model
{
    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @param  Builder<self>  $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('notified_at');
    }
}

<?php

namespace App\Domain\Order\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Settings\CostSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_id', 'product_variant_id', 'name', 'variant_label', 'sku', 'image', 'unit_price', 'unit_cost', 'unit_extra_cost', 'quantity', 'preorder', 'line_total', 'delivery_cost', 'order_bump_id'])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'unit_cost' => 'integer',
            'unit_extra_cost' => 'integer',
            'delivery_cost' => 'integer',
            'quantity' => 'integer',
            'preorder' => 'boolean',
            'line_total' => 'integer',
        ];
    }

    /** Every way an item is created (checkout, admin, recovered carts) keeps the product's costs of that moment. */
    protected static function booted(): void
    {
        static::creating(function (self $item) {
            $product = $item->product_id && $item->unit_cost === null ? Product::find($item->product_id) : null;
            if ($product) {
                $variantCost = $item->product_variant_id ? ProductVariant::whereKey($item->product_variant_id)->value('cost_price') : null;
                $item->unit_cost = $variantCost ?? $product->cost_price;
                $item->unit_extra_cost = $product->extraCostPerUnit(app(CostSettings::class));
            }
        });
    }

    /** Everything this line cost the shop; null when the purchase price isn't known. */
    public function totalCost(): ?int
    {
        return $this->unit_cost === null ? null : $this->quantity * ($this->unit_cost + $this->unit_extra_cost) + $this->delivery_cost;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

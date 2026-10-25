<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Enums\DiscountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['flash_sale_id', 'product_id', 'discount_type', 'discount_value', 'sort_order'])]
class FlashSaleItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['discount_type' => DiscountType::class, 'discount_value' => 'integer'];
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function apply(int $price): int
    {
        return $price - $this->discount_type->discount($price, $this->discount_value);
    }
}

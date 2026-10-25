<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bundle_id', 'product_id', 'quantity', 'sort_order'])]
class BundleItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

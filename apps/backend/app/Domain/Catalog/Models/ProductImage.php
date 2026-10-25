<?php

namespace App\Domain\Catalog\Models;

use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'path', 'alt', 'sort_order'])]
class ProductImage extends Model
{
    use RevalidatesStorefront;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

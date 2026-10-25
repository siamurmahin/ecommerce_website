<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Catalog\Models\Product;
use App\Support\HasLocalizedFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One-tick add-on offered at checkout at a special price. Simple products only. */
#[Fillable([
    'product_id', 'title', 'title_en', 'description', 'description_en', 'price', 'min_subtotal',
    'trigger_product_ids', 'is_active', 'sort_order',
])]
class OrderBump extends Model
{
    use HasLocalizedFields;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'min_subtotal' => 'integer',
            'trigger_product_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Offered for a cart with these product ids and this subtotal.
     *
     * @param  list<int>  $cartProductIds
     */
    public function eligibleFor(array $cartProductIds, int $subtotal): bool
    {
        $triggers = array_map('intval', $this->trigger_product_ids ?? []);

        return $this->is_active
            && $subtotal >= $this->min_subtotal
            && ($triggers === [] || array_intersect($triggers, $cartProductIds) !== []);
    }
}

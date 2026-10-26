<?php

namespace App\Domain\Catalog\Models;

use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['product_id', 'sku', 'price', 'compare_price', 'cost_price', 'stock', 'image', 'is_active', 'sort_order'])]
class ProductVariant extends Model
{
    use RevalidatesStorefront;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost_price' => 'integer',
            'compare_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class)->with('attribute');
    }

    /** e.g. "M / সাদা" */
    public function label(?string $locale = null): string
    {
        return $this->attributeValues
            ->sortBy(fn (AttributeValue $v) => $v->attribute->sort_order)
            ->map(fn (AttributeValue $v) => $v->localized('value', $locale))
            ->implode(' / ');
    }
}

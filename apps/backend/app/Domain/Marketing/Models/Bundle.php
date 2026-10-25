<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Marketing\Enums\DiscountType;
use App\Support\HasLocalizedFields;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** "Buy these together and save": the discount applies automatically when all items are in the cart. */
#[Fillable(['name', 'name_en', 'discount_type', 'discount_value', 'is_active', 'sort_order'])]
class Bundle extends Model
{
    use HasLocalizedFields, RevalidatesStorefront;

    protected function casts(): array
    {
        return ['discount_type' => DiscountType::class, 'discount_value' => 'integer', 'is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class)->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

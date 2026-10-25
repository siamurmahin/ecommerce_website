<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Models\Order;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Single-product page for ad traffic: admin-built blocks with the order form on the page itself. */
/**
 * A/B testing: a variant is a full page of its own with `variant_of_id` set. Visitors of the main
 * page's link are split between it and its active variants by `traffic_weight` (storefront proxy).
 */
#[Fillable(['slug', 'title', 'product_id', 'blocks', 'meta_title', 'meta_description', 'og_image', 'is_indexable', 'is_active', 'variant_of_id', 'traffic_weight'])]
class LandingPage extends Model
{
    use RevalidatesStorefront;

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_indexable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'variant_of_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'variant_of_id');
    }

    public function isVariant(): bool
    {
        return $this->variant_of_id !== null;
    }

    /** Orders per 100 views (cancelled ones excluded by the caller's count). */
    public static function conversionRate(int $orders, int $views): ?float
    {
        return $views > 0 ? round($orders / $views * 100, 1) : null;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function storefrontTags(): array
    {
        $old = $this->getOriginal('slug');

        // The split lives in the main page's response too, and the proxy's variant map under 'landings'.
        $parent = $this->variant_of_id ? $this->parent?->slug : null;

        return array_values(array_unique(array_filter(['landings', "landing:{$this->slug}", $old ? "landing:{$old}" : null, $parent ? "landing:{$parent}" : null])));
    }
}

<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductBadge;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Marketing\Models\FlashSaleItem;
use App\Domain\Marketing\Support\ActiveFlashSales;
use App\Domain\Review\Models\Review;
use App\Settings\CostSettings;
use App\Support\HasLocalizedFields;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'category_id', 'brand_id', 'name', 'name_en', 'slug', 'sku', 'type',
    'short_description', 'short_description_en', 'description', 'description_en',
    'price', 'compare_price', 'cost_price', 'extra_costs', 'shared_costs', 'stock', 'low_stock_threshold', 'preorder', 'preorder_note', 'preorder_note_en', 'badge',
    'is_active', 'is_featured', 'free_delivery', 'owner_pays_delivery', 'delivery_cost', 'weight_grams', 'specifications',
    'meta_title', 'meta_description', 'published_at',
])]
class Product extends Model
{
    use HasLocalizedFields, LogsActivity, RevalidatesStorefront, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'badge' => ProductBadge::class,
            'price' => 'integer',
            'compare_price' => 'integer',
            'cost_price' => 'integer',
            'extra_costs' => 'array',
            'shared_costs' => 'boolean',
            'owner_pays_delivery' => 'boolean',
            'delivery_cost' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'free_delivery' => 'boolean',
            'preorder' => 'boolean',
            'specifications' => 'array',
            'published_at' => 'datetime',
            'rating_avg' => 'float',
        ];
    }

    /** Per-unit costs besides the purchase price: its own (wrapping, carrying…) plus the shop-wide ones, poisha. */
    public function extraCostPerUnit(CostSettings $settings): int
    {
        $own = array_sum(array_map(fn (array $c) => (int) ($c['amount'] ?? 0), $this->extra_costs ?? []));

        return (int) $own + ($this->shared_costs ? $settings->sharedPerUnit() : 0);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Recount approved reviews into rating_avg / reviews_count (no activity log, no extra revalidation). */
    public function refreshRating(): void
    {
        $stats = $this->reviews()->approved()->selectRaw('count(*) as total, avg(rating) as average')->first();

        $this->forceFill([
            'reviews_count' => (int) $stats->total,
            'rating_avg' => round((float) $stats->average, 2),
        ])->saveQuietly();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    /** Visible on the storefront: active and published (or no publish date). */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isVariable(): bool
    {
        return $this->type === ProductType::Variable;
    }

    /** Lowest regular price across active variants (variable) or the product price (simple). */
    public function basePrice(): int
    {
        if ($this->isVariable() && $this->relationLoaded('variants')) {
            return (int) ($this->variants->where('is_active', true)->min('price') ?? $this->price);
        }

        return $this->price;
    }

    /** What the customer pays from: the base price with any running flash sale applied. */
    public function displayPrice(): int
    {
        return $this->salePrice($this->basePrice());
    }

    /** Selling price of one unit (of a variant), flash sale included. Cart and product API both use this. */
    public function unitPrice(?ProductVariant $variant = null): int
    {
        return $this->salePrice($variant?->price ?? $this->price);
    }

    public function salePrice(int $regular): int
    {
        return app(ActiveFlashSales::class)->price($this->id, $regular);
    }

    public function flashSaleItem(): ?FlashSaleItem
    {
        return app(ActiveFlashSales::class)->for($this->id);
    }

    /** Pricier alternatives shown on this product's page. */
    public function upsells(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_relations', 'product_id', 'related_id')
            ->withPivotValue('type', 'upsell')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    /** Products that list this one as a cross-sell (inverse of crossSells). */
    public function crossSellOf(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_relations', 'related_id', 'product_id')->withPivotValue('type', 'cross_sell');
    }

    /** Add-ons suggested in the cart when this product is in it. */
    public function crossSells(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_relations', 'product_id', 'related_id')
            ->withPivotValue('type', 'cross_sell')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function totalStock(): int
    {
        if ($this->isVariable()) {
            return (int) $this->variants->where('is_active', true)->sum('stock');
        }

        return $this->stock;
    }
}

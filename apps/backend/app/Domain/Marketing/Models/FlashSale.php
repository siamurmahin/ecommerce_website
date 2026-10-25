<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Marketing\Support\ActiveFlashSales;
use App\Support\HasLocalizedFields;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'name_en', 'starts_at', 'ends_at', 'is_active'])]
class FlashSale extends Model
{
    use HasLocalizedFields, RevalidatesStorefront;

    protected static function booted(): void
    {
        static::saved(fn () => app(ActiveFlashSales::class)->flush());
        static::deleted(fn () => app(ActiveFlashSales::class)->flush());
    }

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class)->orderBy('sort_order');
    }

    public function scopeRunning(Builder $query): void
    {
        $query->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    public function isRunning(): bool
    {
        return $this->is_active && $this->starts_at->lte(now()) && $this->ends_at->gt(now());
    }
}

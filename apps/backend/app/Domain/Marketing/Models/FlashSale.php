<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Marketing\Support\ActiveFlashSales;
use App\Support\HasLocalizedFields;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'name_en', 'starts_at', 'ends_at', 'is_active', 'repeats_weekly'])]
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
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean', 'repeats_weekly' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class)->orderBy('sort_order');
    }

    public function scopeRunning(Builder $query): void
    {
        $query->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    /** A weekly sale whose window has passed moves to the same weekday and time next week (or later). */
    public function rollForward(): bool
    {
        if (! $this->repeats_weekly || $this->ends_at->isFuture()) {
            return false;
        }
        $weeks = (int) ceil($this->ends_at->diffInSeconds(now()) / (7 * 86400)) ?: 1;
        $this->update(['starts_at' => $this->starts_at->addWeeks($weeks), 'ends_at' => $this->ends_at->addWeeks($weeks)]);

        return true;
    }

    public function isRunning(): bool
    {
        return $this->is_active && $this->starts_at->lte(now()) && $this->ends_at->gt(now());
    }
}

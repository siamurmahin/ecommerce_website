<?php

namespace App\Domain\Shipping\Models;

use App\Support\HasLocalizedFields;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'name_en', 'charge', 'eta_min_days', 'eta_max_days', 'is_default', 'is_active', 'sort_order'])]
class DeliveryZone extends Model
{
    use HasLocalizedFields, RevalidatesStorefront;

    protected function casts(): array
    {
        return [
            'charge' => 'integer',
            'eta_min_days' => 'integer',
            'eta_max_days' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Only one zone can be the fallback. */
    protected static function booted(): void
    {
        static::saved(function (self $zone) {
            if ($zone->is_default) {
                static::whereKeyNot($zone->id)->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Zone for districts that weren't assigned one ("ঢাকার বাইরে"). */
    public static function fallback(): ?self
    {
        return static::active()->orderByDesc('is_default')->orderBy('sort_order')->first();
    }

    public function storefrontTags(): array
    {
        return ['shipping'];
    }
}

<?php

namespace App\Domain\Shipping\Models;

use App\Support\HasLocalizedFields;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Thana / upazila inside a district. Optional on orders, but couriers want it. */
#[Fillable(['district_id', 'name', 'name_en', 'is_active'])]
class Thana extends Model
{
    use HasLocalizedFields, RevalidatesStorefront;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function storefrontTags(): array
    {
        return ['shipping'];
    }
}

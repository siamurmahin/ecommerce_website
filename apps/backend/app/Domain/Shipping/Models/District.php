<?php

namespace App\Domain\Shipping\Models;

use App\Support\HasLocalizedFields;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['division', 'division_en', 'name', 'name_en', 'delivery_zone_id'])]
class District extends Model
{
    use HasLocalizedFields, RevalidatesStorefront;

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function thanas(): HasMany
    {
        return $this->hasMany(Thana::class);
    }

    /** The district's own zone when active, otherwise the fallback zone. */
    public function resolveZone(): ?DeliveryZone
    {
        $zone = $this->deliveryZone;

        return $zone?->is_active ? $zone : DeliveryZone::fallback();
    }

    public function storefrontTags(): array
    {
        return ['shipping'];
    }
}

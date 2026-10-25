<?php

namespace App\Domain\Customer\Models;

use App\Domain\Shipping\Models\District;
use App\Domain\Shipping\Models\Thana;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['label', 'name', 'phone', 'district_id', 'thana_id', 'address', 'is_default'])]
class CustomerAddress extends Model
{
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Exactly one default: a new default demotes the rest, and the first address is the default.
        static::saving(function (CustomerAddress $address) {
            if (! $address->is_default && ! static::where('customer_id', $address->customer_id)->whereKeyNot($address->id)->exists()) {
                $address->is_default = true;
            }
        });

        static::saved(function (CustomerAddress $address) {
            if ($address->is_default) {
                static::where('customer_id', $address->customer_id)->whereKeyNot($address->id)->update(['is_default' => false]);
            }
        });

        static::deleted(function (CustomerAddress $address) {
            if ($address->is_default) {
                static::where('customer_id', $address->customer_id)->latest('id')->first()?->update(['is_default' => true]);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function thana(): BelongsTo
    {
        return $this->belongsTo(Thana::class);
    }
}

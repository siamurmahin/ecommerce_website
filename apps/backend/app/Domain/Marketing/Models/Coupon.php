<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Marketing\Enums\CouponType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'description', 'type', 'value', 'max_discount', 'min_subtotal', 'starts_at', 'ends_at',
    'usage_limit', 'per_phone_limit', 'product_ids', 'category_ids', 'is_active',
])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'max_discount' => 'integer',
            'min_subtotal' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'product_ids' => 'array',
            'category_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** Codes are case-insensitive: stored and looked up upper-case. */
    protected function code(): Attribute
    {
        return Attribute::set(fn (string $value) => strtoupper(trim($value)));
    }

    public static function findByCode(string $code): ?self
    {
        return static::firstWhere('code', strtoupper(trim($code)));
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isLimitedToItems(): bool
    {
        return filled($this->product_ids) || filled($this->category_ids);
    }
}

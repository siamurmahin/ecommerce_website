<?php

namespace App\Domain\Customer\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Order\Models\Order;
use App\Domain\Review\Models\Review;
use App\Domain\Reward\Models\PointTransaction;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Storefront customer. Signs in with a one-time code by SMS (phone) or email and holds Sanctum tokens; never reaches
 * the admin panel. Accounts made by email have no phone until they verify one.
 */
#[Fillable(['phone', 'name', 'email', 'is_blocked', 'blocked_reason', 'note', 'last_login_at', 'referred_by_id'])]
class Customer extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasApiTokens;

    protected function casts(): array
    {
        return [
            'is_blocked' => 'boolean',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'points_balance' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // An address typed in the profile is not proof of owning it: only the email-code flow verifies one.
        static::saving(function (Customer $customer) {
            if ($customer->isDirty('email')) {
                $customer->email = filled($customer->email) ? mb_strtolower(trim($customer->email)) : null;
                if (! $customer->isDirty('email_verified_at')) {
                    $customer->email_verified_at = null;
                }
            }
        });

        // A blocked customer is signed out everywhere at once.
        static::saved(function (Customer $customer) {
            if ($customer->wasChanged('is_blocked') && $customer->is_blocked) {
                $customer->tokens()->delete();
            }
        });
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('is_default')->latest('id');
    }

    /** Every order placed with this phone, before or after signing up. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_phone', 'phone');
    }

    /**
     * What the account page lists: orders placed while signed in, plus every order with the verified phone
     * (guest orders from before sign-up included). An email-only account never sees orders by phone.
     */
    public function accountOrders(): Builder
    {
        return Order::query()->where(fn (Builder $q) => $q->where('customer_id', $this->id)
            ->when($this->phone, fn (Builder $q) => $q->orWhere('customer_phone', $this->phone)));
    }

    public static function findByVerifiedEmail(string $email): ?self
    {
        return static::where('email', mb_strtolower(trim($email)))->whereNotNull('email_verified_at')->first();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Saved products, newest first. */
    public function wishlist(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlist_items')->withTimestamps()->orderByPivot('created_at', 'desc')->orderByPivot('id', 'desc');
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class)->latest('id');
    }

    /** Their code for inviting friends, made on first use (no 0/O or 1/I to misread). */
    public function referralCode(): string
    {
        while (! $this->referral_code) {
            $code = substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 3)), 0, 6);
            if (! static::where('referral_code', $code)->exists() && ! Coupon::findByCode($code)) {
                $this->forceFill(['referral_code' => $code])->save();
            }
        }

        return $this->referral_code;
    }

    public static function isBlocked(string $phone): bool
    {
        return static::where('phone', $phone)->where('is_blocked', true)->exists();
    }
}

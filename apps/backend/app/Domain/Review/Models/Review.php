<?php

namespace App\Domain\Review\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Models\Order;
use App\Domain\Review\Enums\ReviewStatus;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Product review. Shown on the storefront only once approved; the product keeps a cached average and count. */
#[Fillable(['product_id', 'customer_id', 'order_id', 'name', 'rating', 'body', 'images', 'status', 'source', 'is_verified', 'reply', 'replied_at', 'approved_at'])]
class Review extends Model
{
    use RevalidatesStorefront;

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'images' => 'array',
            'is_verified' => 'boolean',
            'replied_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Review $review) {
            if ($review->isDirty('status') && $review->status === ReviewStatus::Approved) {
                $review->approved_at ??= now();
            }
            if ($review->isDirty('reply')) {
                $review->replied_at = filled($review->reply) ? now() : null;
            }
        });

        $refresh = function (Review $review) {
            $review->product?->refreshRating();

            // Moved to another product (admin edit): fix the old one too.
            if ($review->wasChanged('product_id') && $old = $review->getOriginal('product_id')) {
                Product::find($old)?->refreshRating();
            }
        };

        static::saved($refresh);
        static::deleted($refresh);
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', ReviewStatus::Approved);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

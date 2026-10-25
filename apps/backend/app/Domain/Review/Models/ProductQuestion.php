<?php

namespace App\Domain\Review\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\Customer;
use App\Domain\Review\Enums\QuestionStatus;
use App\Models\User;
use App\Support\RevalidatesStorefront;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A shopper's question on a product page. Shown publicly once staff answer (or publish) it. */
#[Fillable(['product_id', 'customer_id', 'name', 'phone', 'question', 'answer', 'status', 'answered_by', 'answered_at', 'ip'])]
class ProductQuestion extends Model
{
    use RevalidatesStorefront;

    protected function casts(): array
    {
        return [
            'status' => QuestionStatus::class,
            'answered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductQuestion $q) {
            if ($q->isDirty('answer')) {
                $q->answered_at = filled($q->answer) ? now() : null;
                // Answering a waiting question publishes it.
                if (filled($q->answer) && $q->status === QuestionStatus::Pending) {
                    $q->status = QuestionStatus::Published;
                }
            }
        });
    }

    public function storefrontTags(): array
    {
        return ['catalog'];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', QuestionStatus::Published);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}

<?php

namespace App\Domain\Order\Models;

use App\Domain\Fraud\Models\FraudCheck;
use App\Domain\Order\Enums\IncompleteStatus;
use App\Domain\Shipping\Models\District;
use App\Domain\Shipping\Models\Thana;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Checkout details saved before the customer pressed "place order", for a follow-up call. */
#[Fillable([
    'draft_id', 'customer_name', 'customer_phone', 'district_id', 'thana_id', 'address', 'customer_note',
    'items', 'subtotal', 'status', 'order_id', 'user_id', 'staff_note', 'contacted_at',
    'utm', 'locale', 'ip', 'user_agent', 'last_activity_at', 'recovery_token', 'recovery_sms_at', 'recovered_at',
])]
class IncompleteOrder extends Model
{
    use MassPrunable;

    protected function casts(): array
    {
        return [
            'status' => IncompleteStatus::class,
            'items' => 'array',
            'utm' => 'array',
            'contacted_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'recovery_sms_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function thana(): BelongsTo
    {
        return $this->belongsTo(Thana::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fraudCheck(): HasOne
    {
        return $this->hasOne(FraudCheck::class, 'phone', 'customer_phone');
    }

    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', [IncompleteStatus::Open, IncompleteStatus::Contacted]);
    }

    /** Old leads are deleted by the daily `model:prune`. */
    public function prunable(): Builder
    {
        return static::where('last_activity_at', '<', now()->subDays(60));
    }
}

<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Stock bought from a supplier; receiving it adds the quantities to stock. Money is poisha. */
#[Fillable(['number', 'supplier_id', 'status', 'ordered_at', 'expected_at', 'received_at', 'total', 'paid_amount', 'note', 'user_id'])]
class PurchaseOrder extends Model
{
    protected $attributes = ['status' => 'draft', 'total' => 0, 'paid_amount' => 0];

    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'ordered_at' => 'date',
            'expected_at' => 'date',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (self $po) => $po->updateQuietly(['number' => 'PO-'.(1000 + $po->id)]));
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recalculate(): void
    {
        $this->updateQuietly(['total' => (int) $this->items()->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as t')->value('t')]);
    }

    public function due(): int
    {
        return max(0, $this->total - $this->paid_amount);
    }
}

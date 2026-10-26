<?php

namespace App\Domain\Returns\Models;

use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Returns\Enums\ReturnReason;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Enums\ReturnType;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** A return or exchange of some (or all) items of a delivered order. Refund in poisha. */
#[Fillable([
    'order_id', 'type', 'reason', 'status', 'items', 'note', 'staff_note', 'refund_amount', 'refund_method', 'restocked_cost',
    'exchange_order_id', 'source', 'user_id', 'received_at', 'resolved_at',
])]
class OrderReturn extends Model
{
    public const REFUND_METHODS = ['bkash' => 'বিকাশ', 'nagad' => 'নগদ', 'rocket' => 'রকেট', 'bank' => 'ব্যাংক', 'cash' => 'ক্যাশ', 'store_credit' => 'পরের অর্ডারে ছাড়'];

    protected function casts(): array
    {
        return [
            'type' => ReturnType::class,
            'reason' => ReturnReason::class,
            'status' => ReturnStatus::class,
            'items' => 'array',
            'refund_amount' => 'integer',
            'restocked_cost' => 'integer',
            'received_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function exchangeOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'exchange_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', array_map(fn (ReturnStatus $s) => $s->value, array_filter(ReturnStatus::cases(), fn ($s) => $s->isOpen())));
    }

    /** order_item_id => quantity being sent back. */
    public function quantities(): Collection
    {
        return collect($this->items)->mapWithKeys(fn (array $i) => [(int) $i['order_item_id'] => (int) $i['quantity']])->filter();
    }

    /** "উদ আতর × 1, টুপি × 2" */
    public function summary(): string
    {
        $items = OrderItem::whereKey($this->quantities()->keys())->pluck('name', 'id');

        return $this->quantities()->map(fn (int $qty, int $id) => ($items[$id] ?? '—')." × {$qty}")->implode(', ');
    }

    /** What the returned pieces cost the shop (purchase + per-unit extras). */
    public function itemsCost(): int
    {
        $items = OrderItem::whereKey($this->quantities()->keys())->get()->keyBy('id');

        return $this->quantities()->sum(fn (int $qty, int $id) => isset($items[$id]) ? ((int) $items[$id]->unit_cost + $items[$id]->unit_extra_cost) * $qty : 0);
    }

    /** What the returned pieces were sold for — the most a refund can be. */
    public function itemsValue(): int
    {
        $items = OrderItem::whereKey($this->quantities()->keys())->get()->keyBy('id');

        return $this->quantities()->sum(fn (int $qty, int $id) => isset($items[$id]) ? $items[$id]->unit_price * $qty : 0);
    }
}

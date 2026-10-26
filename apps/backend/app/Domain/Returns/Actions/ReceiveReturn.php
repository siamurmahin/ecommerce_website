<?php

namespace App\Domain\Returns\Actions;

use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Support\Inventory;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Enums\ReturnType;
use App\Domain\Returns\Models\OrderReturn;
use Illuminate\Support\Facades\DB;

/**
 * The parcel is back: the returned pieces go back into stock. When a return covers every piece of the
 * order, the order itself becomes "returned" (which restocks it and takes it out of sales) instead.
 */
final class ReceiveReturn
{
    public function __construct(private Inventory $inventory, private ChangeOrderStatus $status) {}

    public function handle(OrderReturn $return, bool $restock = true, ?int $userId = null): void
    {
        DB::transaction(function () use ($return, $restock, $userId) {
            $order = $return->order;
            $whole = $return->type === ReturnType::Return
                && $order->items->every(fn ($item) => ($return->quantities()[$item->id] ?? 0) >= $item->quantity);

            if ($whole && $order->status !== OrderStatus::Returned) {
                $this->status->handle($order, OrderStatus::Returned, 'রিটার্ন #'.$return->id.' — '.$return->reason->getLabel(), $userId);
            } elseif ($restock) {
                $this->inventory->restockItems($order, $return->quantities(), "{$order->number} রিটার্ন #{$return->id}");
            }

            $return->update([
                'status' => ReturnStatus::Received, 'received_at' => now(),
                'restocked_cost' => ! $whole && $restock ? $return->itemsCost() : 0,
            ]);
        });
    }
}

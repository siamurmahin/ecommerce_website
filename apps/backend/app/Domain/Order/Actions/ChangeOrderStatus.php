<?php

namespace App\Domain\Order\Actions;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Events\OrderStatusChanged;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Support\Inventory;
use Illuminate\Support\Facades\DB;

final class ChangeOrderStatus
{
    public function __construct(private Inventory $inventory) {}

    public function handle(Order $order, OrderStatus $to, ?string $note = null, ?int $userId = null): void
    {
        $from = $order->status;

        if ($from === $to && blank($note)) {
            return;
        }

        DB::transaction(function () use ($order, $from, $to, $note, $userId) {
            if (! $from->releasesStock() && $to->releasesStock()) {
                $this->inventory->restock($order->loadMissing('items'));
            } elseif ($from->releasesStock() && ! $to->releasesStock()) {
                $this->inventory->deduct($order->loadMissing('items'));
            }

            $order->update(['status' => $to]);
            $order->statusHistories()->create([
                'from' => $from,
                'to' => $to,
                'user_id' => $userId,
                'note' => $note,
            ]);

            if ($from !== $to) {
                DB::afterCommit(fn () => OrderStatusChanged::dispatch($order, $from, $to));
            }
        });
    }
}

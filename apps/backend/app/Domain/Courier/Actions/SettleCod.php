<?php

namespace App\Domain\Courier\Actions;

use App\Domain\Order\Actions\RecordPayment;
use App\Domain\Order\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * The courier paid out an order's cash on delivery: note what arrived, the courier's fee and the payout
 * reference, and record the money as paid on the order.
 */
final class SettleCod
{
    public function __construct(private RecordPayment $payments) {}

    /** $collected/$fee in poisha; null collected = exactly what was expected. */
    public function handle(Order $order, ?int $collected = null, ?int $fee = null, ?string $reference = null, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $collected, $fee, $reference, $userId) {
            $expected = $order->codExpected();
            $collected ??= $expected;

            if ($collected > 0 && ! $order->cod_settled_at) {
                $this->payments->approve($order, $collected, $userId, ['note' => trim('কুরিয়ার COD '.($reference ?? ''))]);
            }

            $order->update([
                'cod_expected' => $expected,
                'cod_collected' => $collected,
                'courier_fee' => $fee ?? $order->courier_fee ?? $order->latestShipment?->delivery_fee,
                'cod_settled_at' => $order->cod_settled_at ?? now(),
                'cod_settlement_ref' => $reference ?: $order->cod_settlement_ref,
            ]);

            return $order;
        });
    }

    /** Payout difference: collected − expected (negative = the courier paid less). */
    public static function difference(Order $order): ?int
    {
        return $order->cod_collected === null ? null : $order->cod_collected - $order->codExpected();
    }
}

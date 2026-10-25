<?php

namespace App\Domain\Order\Actions;

use App\Domain\Order\Enums\PaymentMethod;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\Order;
use Illuminate\Validation\ValidationException;

/** Staff confirm (or turn down) money sent by mobile wallet. */
final class RecordPayment
{
    /**
     * Mark $amount (poisha, on top of anything already confirmed) as received. The order becomes
     * Paid once confirmed money covers the total, otherwise Partial.
     */
    public function approve(Order $order, int $amount, ?int $userId = null, array $details = []): Order
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'টাকার পরিমাণ দিন।']);
        }

        $trx = isset($details['trx_id']) ? strtoupper(trim($details['trx_id'])) : null;
        if ($trx && Order::where('payment_trx_id', $trx)->whereKeyNot($order->id)->exists()) {
            throw ValidationException::withMessages(['trx_id' => 'এই ট্রানজেকশন আইডি অন্য অর্ডারে আছে।']);
        }

        $paid = min($order->total, $order->paid_amount + $amount);
        $order->update(array_filter([
            'paid_amount' => $paid,
            'payment_status' => $paid >= $order->total ? PaymentStatus::Paid : PaymentStatus::Partial,
            'payment_verified_at' => now(),
            'payment_verified_by' => $userId,
            'payment_note' => $details['note'] ?? null,
            'payment_wallet' => isset($details['wallet']) ? PaymentMethod::from($details['wallet']) : null,
            'payment_sender' => $details['sender'] ?? null,
            'payment_trx_id' => $trx,
        ], fn ($v) => $v !== null));

        return $order;
    }

    /** The transaction didn't check out: back to unpaid, keeping what the customer typed for reference. */
    public function reject(Order $order, string $reason, ?int $userId = null): Order
    {
        $order->update([
            'payment_status' => $order->paid_amount > 0 ? PaymentStatus::Partial : PaymentStatus::Unpaid,
            'payment_verified_at' => now(),
            'payment_verified_by' => $userId,
            'payment_note' => $reason,
        ]);

        return $order;
    }
}

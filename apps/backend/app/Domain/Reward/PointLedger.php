<?php

namespace App\Domain\Reward;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Reward\Models\PointTransaction;
use App\Settings\RewardSettings;
use Illuminate\Support\Facades\DB;

/**
 * Posts point changes and keeps an order's points in step with its status: redeemed points are
 * returned if it's cancelled or returned, purchase and referral points arrive only once it's
 * delivered (and go back if it's returned). Re-running for the same status changes nothing.
 */
final class PointLedger
{
    public function __construct(private RewardSettings $settings) {}

    public function post(Customer $customer, int $points, string $type, ?Order $order = null, ?string $note = null, ?int $userId = null): void
    {
        if ($points === 0) {
            return;
        }

        DB::transaction(function () use ($customer, $points, $type, $order, $note, $userId) {
            PointTransaction::create([
                'customer_id' => $customer->id, 'order_id' => $order?->id, 'type' => $type,
                'points' => $points, 'note' => $note, 'user_id' => $userId,
            ]);
            Customer::whereKey($customer->id)->increment('points_balance', $points);
        });
        $customer->points_balance += $points;
    }

    public function syncOrder(Order $order): void
    {
        $owner = Customer::firstWhere('phone', $order->customer_phone);
        $lost = in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Returned], true);
        $delivered = $order->status === OrderStatus::Delivered;

        if ($owner) {
            $this->settle($owner, $order, PointTransaction::REDEEM, $lost ? 0 : -$order->points_redeemed);
            $earned = $this->settledFor($order, PointTransaction::EARN, $owner);
            // The rate in force when the order was delivered is kept, even if it changes later.
            $this->settle($owner, $order, PointTransaction::EARN, $delivered ? ($earned ?: $this->settings->pointsFor($order->subtotal - $order->discount)) : 0);
        }

        if ($order->referrer_id && $referrer = Customer::find($order->referrer_id)) {
            $bonus = $this->settledFor($order, PointTransaction::REFERRAL, $referrer);
            $this->settle($referrer, $order, PointTransaction::REFERRAL, $delivered ? ($bonus ?: ($this->settings->referral_enabled ? $this->settings->referrer_points : 0)) : 0);
        }
    }

    private function settledFor(Order $order, string $type, Customer $customer): int
    {
        return (int) PointTransaction::where('order_id', $order->id)->where('type', $type)->where('customer_id', $customer->id)->sum('points');
    }

    private function settle(Customer $customer, Order $order, string $type, int $target): void
    {
        $this->post($customer, $target - $this->settledFor($order, $type, $customer), $type, $order, $order->number);
    }
}

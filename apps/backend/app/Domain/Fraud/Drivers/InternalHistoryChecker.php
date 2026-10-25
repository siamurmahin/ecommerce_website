<?php

namespace App\Domain\Fraud\Drivers;

use App\Domain\Fraud\Contracts\FraudChecker;
use App\Domain\Fraud\FraudReport;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;

/** Our own orders only: free, instant, but blind to the customer's history with other shops. */
final class InternalHistoryChecker implements FraudChecker
{
    public static function label(): string
    {
        return 'শুধু নিজেদের অর্ডার হিস্ট্রি (ফ্রি)';
    }

    public function check(string $phone): FraudReport
    {
        $counts = Order::where('customer_phone', $phone)
            ->selectRaw('status, count(*) as aggregate')->groupBy('status')
            ->pluck('aggregate', 'status');

        return new FraudReport(
            total: (int) $counts->sum(),
            delivered: (int) ($counts[OrderStatus::Delivered->value] ?? 0),
            cancelled: (int) (($counts[OrderStatus::Cancelled->value] ?? 0) + ($counts[OrderStatus::Returned->value] ?? 0)),
        );
    }
}

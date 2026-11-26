<?php

namespace App\Domain\Fraud\Drivers;

use App\Domain\Courier\CourierException;
use App\Domain\Courier\Drivers\SteadfastCourier;
use App\Domain\Fraud\Contracts\FraudChecker;
use App\Domain\Fraud\FraudReport;
use RuntimeException;

/** Steadfast's own fraud_check: the phone's parcels across every Steadfast merchant. Uses the Steadfast API key. */
final class SteadfastFraudChecker implements FraudChecker
{
    public function __construct(private SteadfastCourier $steadfast) {}

    public static function label(): string
    {
        return 'Steadfast (শুধু Steadfast-এর হিস্ট্রি, কুরিয়ারের API key দিয়েই)';
    }

    public function check(string $phone): FraudReport
    {
        try {
            $history = $this->steadfast->fraudHistory($phone);
        } catch (CourierException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        return new FraudReport(
            total: $history['total'],
            delivered: $history['delivered'],
            cancelled: $history['cancelled'],
            couriers: $history['total'] > 0 ? ['steadfast' => $history] : [],
        );
    }
}

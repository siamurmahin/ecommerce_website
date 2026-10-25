<?php

namespace App\Domain\Fraud;

use App\Domain\Fraud\Contracts\FraudChecker;
use App\Domain\Fraud\Models\FraudCheck;
use App\Settings\CourierSettings;

/** Runs the configured checker and caches the result per phone. */
final class FraudService
{
    public function __construct(private CourierSettings $settings) {}

    /** @throws \RuntimeException from the checker */
    public function check(string $phone, bool $fresh = false): FraudCheck
    {
        $cached = FraudCheck::firstWhere('phone', $phone);
        $source = $this->source();

        if (! $fresh && $cached && $cached->source === $source && $cached->checked_at->gt(now()->subHours($this->settings->fraud_cache_hours))) {
            return $cached;
        }

        $report = $this->checker()->check($phone);

        return FraudCheck::updateOrCreate(['phone' => $phone], [
            'source' => $source,
            'total' => $report->total,
            'delivered' => $report->delivered,
            'cancelled' => $report->cancelled,
            'success_ratio' => $report->successRatio(),
            'couriers' => $report->couriers ?: null,
            'checked_at' => now(),
        ]);
    }

    public function checker(): FraudChecker
    {
        return app(config('courier.fraud_checkers.'.$this->source()));
    }

    private function source(): string
    {
        return array_key_exists($this->settings->fraud_driver, config('courier.fraud_checkers')) ? $this->settings->fraud_driver : 'internal';
    }
}

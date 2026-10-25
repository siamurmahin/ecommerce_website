<?php

namespace App\Domain\Fraud\Drivers;

use App\Domain\Fraud\Contracts\FraudChecker;
use App\Domain\Fraud\FraudReport;
use App\Settings\CourierSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** bdcourier.com: one lookup across Pathao, Steadfast, RedX, Paperfly and more. Paid API key. */
final class BdCourierChecker implements FraudChecker
{
    public function __construct(private CourierSettings $settings) {}

    public static function label(): string
    {
        return 'BD Courier (সব কুরিয়ারের হিস্ট্রি, API key লাগবে)';
    }

    public function check(string $phone): FraudReport
    {
        if (blank($this->settings->fraud_api_key)) {
            throw new RuntimeException('BD Courier API key সেট করা নেই (সেটিংস → কুরিয়ার)।');
        }

        $response = Http::timeout(15)->acceptJson()->withToken($this->settings->fraud_api_key)
            ->post(config('courier.bdcourier.url'), ['phone' => $phone]);

        $data = $response->json('courierData');
        if (! $response->successful() || ! is_array($data)) {
            throw new RuntimeException('BD Courier: '.($response->json('message') ?? $response->json('error') ?? "HTTP {$response->status()}"));
        }

        $couriers = collect($data)->except('summary')
            ->filter(fn ($c) => is_array($c) && (int) ($c['total_parcel'] ?? 0) > 0)
            ->map(fn ($c) => [
                'total' => (int) $c['total_parcel'],
                'delivered' => (int) ($c['success_parcel'] ?? 0),
                'cancelled' => (int) ($c['cancelled_parcel'] ?? 0),
            ])->all();

        $summary = $data['summary'] ?? [];

        return new FraudReport(
            total: (int) ($summary['total_parcel'] ?? array_sum(array_column($couriers, 'total'))),
            delivered: (int) ($summary['success_parcel'] ?? array_sum(array_column($couriers, 'delivered'))),
            cancelled: (int) ($summary['cancelled_parcel'] ?? array_sum(array_column($couriers, 'cancelled'))),
            couriers: $couriers,
        );
    }
}

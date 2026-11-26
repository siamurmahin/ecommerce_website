<?php

namespace App\Domain\Courier\Actions;

use App\Domain\Courier\Contracts\PaysOut;
use App\Domain\Courier\CourierException;
use App\Domain\Courier\CourierManager;
use App\Domain\Courier\Enums\ShipmentStatus;
use App\Domain\Courier\Models\Shipment;
use App\Domain\Order\Models\Order;

/**
 * Settle orders from a payout read over the courier's API: every delivered parcel in it is marked as paid
 * with the COD the courier lists, keyed by the payout id. Parcels that brought no cash (cancelled, returned)
 * are skipped. Running it twice changes nothing.
 */
final class ImportPayout
{
    public function __construct(private CourierManager $couriers, private SettleCod $settle, private ApplyShipmentStatus $applyStatus) {}

    /**
     * @return array{settled: int, short: int, already: int, skipped: int, unmatched: list<string>}
     *
     * @throws CourierException
     */
    public function handle(string $courier, string $payoutId, ?int $userId = null): array
    {
        $driver = $this->couriers->driver($courier);
        if (! $driver instanceof PaysOut) {
            throw new CourierException("{$driver::label()}-এর পেমেন্ট API থেকে মেলানো যায় না।");
        }

        $payout = $driver->payout($payoutId);
        $result = ['settled' => 0, 'short' => 0, 'already' => 0, 'skipped' => 0, 'unmatched' => []];

        foreach ($payout->lines as $line) {
            $status = $driver->mapStatus($line->rawStatus);
            if (! in_array($status, [ShipmentStatus::Delivered, ShipmentStatus::PartialDelivered], true)) {
                $result['skipped']++;

                continue;
            }

            $shipment = Shipment::with('order')->where('courier', $courier)->where('consignment_id', $line->consignmentId)->latest('id')->first();
            $order = $shipment?->order ?? ($line->invoice ? Order::where('number', strtoupper($line->invoice))->first() : null);

            if (! $order) {
                $result['unmatched'][] = $line->invoice ?: $line->consignmentId;

                continue;
            }

            // A missed webhook: the payout is proof the parcel was delivered.
            if ($shipment && ! $shipment->status->isFinal()) {
                $this->applyStatus->handle($shipment, $line->rawStatus);
                $order->refresh();
            }

            if ($order->cod_settled_at) {
                $result['already']++;

                continue;
            }

            $this->settle->handle($order, $line->codAmount, null, $payout->id, $userId);
            $result['settled']++;
            if ((SettleCod::difference($order->refresh()) ?? 0) < 0) {
                $result['short']++;
            }
        }

        return $result;
    }
}

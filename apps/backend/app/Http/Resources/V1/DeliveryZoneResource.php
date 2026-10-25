<?php

namespace App\Http\Resources\V1;

use App\Domain\Shipping\Models\DeliveryZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Delivery zone with its charge (poisha) and delivery window in days.
 *
 * @mixin DeliveryZone
 */
class DeliveryZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => (string) $this->localized('name'),
            'charge' => $this->charge,
            'eta_min_days' => $this->eta_min_days,
            'eta_max_days' => $this->eta_max_days,
        ];
    }
}

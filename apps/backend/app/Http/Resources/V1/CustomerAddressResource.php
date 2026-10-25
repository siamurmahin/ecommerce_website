<?php

namespace App\Http\Resources\V1;

use App\Domain\Customer\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerAddress
 */
class CustomerAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'name' => $this->name,
            'phone' => $this->phone,
            'district_id' => $this->district_id,
            /** @var string|null */
            'district' => $this->district?->localized('name'),
            'thana_id' => $this->thana_id,
            /** @var string|null */
            'thana' => $this->thana?->localized('name'),
            'address' => $this->address,
            'is_default' => $this->is_default,
        ];
    }
}

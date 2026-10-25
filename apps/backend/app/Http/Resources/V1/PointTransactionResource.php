<?php

namespace App\Http\Resources\V1;

use App\Domain\Reward\Models\PointTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PointTransaction */
class PointTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** @var 'earn'|'redeem'|'referral'|'adjust' */
            'type' => $this->type,
            'points' => $this->points,
            /** @var string|null */
            'order_number' => $this->order?->number,
            /** Shown for staff adjustments. */
            /** @var string|null */
            'note' => $this->type === PointTransaction::ADJUST ? $this->note : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

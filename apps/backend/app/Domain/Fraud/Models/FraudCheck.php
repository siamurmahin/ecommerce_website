<?php

namespace App\Domain\Fraud\Models;

use App\Settings\CourierSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phone', 'source', 'total', 'delivered', 'cancelled', 'success_ratio', 'couriers', 'checked_at'])]
class FraudCheck extends Model
{
    protected function casts(): array
    {
        return ['couriers' => 'array', 'checked_at' => 'datetime'];
    }

    /** new = no finished parcels yet; risky = below the threshold set in admin. */
    public function risk(): string
    {
        if ($this->success_ratio === null) {
            return 'new';
        }

        $threshold = app(CourierSettings::class)->fraud_risky_below;

        return match (true) {
            $this->success_ratio < $threshold => 'risky',
            $this->success_ratio < min(100, $threshold + 15) => 'medium',
            default => 'good',
        };
    }

    public function riskColor(): string
    {
        return match ($this->risk()) {
            'risky' => 'danger',
            'medium' => 'warning',
            'good' => 'success',
            default => 'gray',
        };
    }

    public function summary(): string
    {
        if ($this->total === 0) {
            return 'কোনো আগের পার্সেল নেই';
        }

        return ($this->success_ratio !== null ? "{$this->success_ratio}% সফল · " : '')
            ."মোট {$this->total}, ডেলিভারড {$this->delivered}, বাতিল/রিটার্ন {$this->cancelled}";
    }
}

<?php

namespace App\Domain\Courier\Models;

use App\Domain\Courier\Contracts\Courier;
use App\Domain\Courier\CourierManager;
use App\Domain\Courier\Enums\ShipmentStatus;
use App\Domain\Order\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'courier', 'consignment_id', 'tracking_code', 'status', 'raw_status',
    'cod_amount', 'delivery_fee', 'user_id', 'payload', 'synced_at',
])]
class Shipment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNotIn('status', array_map(fn (ShipmentStatus $s) => $s->value, array_filter(ShipmentStatus::cases(), fn ($s) => $s->isFinal())));
    }

    public function driver(): Courier
    {
        return app(CourierManager::class)->driver($this->courier);
    }

    public function courierLabel(): string
    {
        $class = config("courier.couriers.{$this->courier}");

        return $class ? $class::label() : $this->courier;
    }

    public function trackingUrl(): ?string
    {
        return $this->driver()->trackingUrl($this);
    }
}

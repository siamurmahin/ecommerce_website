<?php

namespace App\Domain\Order\Models;

use App\Domain\Courier\Models\Shipment;
use App\Domain\Fraud\Models\FraudCheck;
use App\Domain\Marketing\Models\CouponRedemption;
use App\Domain\Marketing\Models\LandingPage;
use App\Domain\Order\Enums\OrderSource;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentMethod;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Support\Inventory;
use App\Domain\Shipping\Models\DeliveryZone;
use App\Domain\Shipping\Models\District;
use App\Domain\Shipping\Models\Thana;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'number', 'access_token', 'status', 'payment_method', 'payment_status',
    'payment_wallet', 'payment_sender', 'payment_trx_id', 'advance_amount', 'paid_amount', 'payment_verified_at', 'payment_verified_by', 'payment_note',
    'customer_name', 'customer_phone', 'customer_email', 'district_id', 'district_name', 'thana_id', 'thana_name', 'address',
    'delivery_zone_id', 'delivery_zone_name', 'customer_note', 'internal_note',
    'subtotal', 'delivery_charge', 'discount', 'coupon_id', 'coupon_code', 'discounts', 'total',
    'source', 'landing_page_id', 'locale', 'event_id', 'utm', 'fbp', 'fbc', 'ttp', 'ttclid', 'points_redeemed', 'referrer_id', 'ga_client_id', 'ga_session_id', 'ip', 'user_agent',
])]
class Order extends Model
{
    use LogsActivity, SoftDeletes;

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'payment_wallet' => PaymentMethod::class,
            'advance_amount' => 'integer',
            'paid_amount' => 'integer',
            'payment_verified_at' => 'datetime',
            'source' => OrderSource::class,
            'subtotal' => 'integer',
            'delivery_charge' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'utm' => 'array',
            'discounts' => 'array',
        ];
    }

    /** What the courier should collect: nothing once fully paid, otherwise the total minus any verified advance. */
    public function dueOnDelivery(): int
    {
        return $this->payment_status === PaymentStatus::Paid ? 0 : max(0, $this->total - $this->paid_amount);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'payment_status', 'payment_trx_id', 'paid_amount', 'customer_name', 'customer_phone', 'address', 'internal_note', 'delivery_charge', 'discount', 'total'])
            ->logOnlyDirty();
    }

    /** A deleted (trashed) order no longer holds stock; restoring it takes the stock again. */
    protected static function booted(): void
    {
        static::softDeleted(function (self $order) {
            if (! $order->status->releasesStock()) {
                app(Inventory::class)->restock($order->loadMissing('items'));
            }
        });

        static::restoring(function (self $order) {
            if (! $order->status->releasesStock()) {
                app(Inventory::class)->deduct($order->loadMissing('items'));
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('id');
    }

    public function couponRedemption(): HasOne
    {
        return $this->hasOne(CouponRedemption::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function latestShipment(): HasOne
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }

    /** Cached delivery history for this phone (see FraudService). */
    public function fraudCheck(): HasOne
    {
        return $this->hasOne(FraudCheck::class, 'phone', 'customer_phone');
    }

    public function thana(): BelongsTo
    {
        return $this->belongsTo(Thana::class);
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}

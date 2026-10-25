<?php

namespace App\Domain\Reward\Models;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One change to a customer's points; the balance on `customers.points_balance` is their sum. */
#[Fillable(['customer_id', 'order_id', 'type', 'points', 'note', 'user_id'])]
class PointTransaction extends Model
{
    public const EARN = 'earn';

    public const REDEEM = 'redeem';

    public const REFERRAL = 'referral';

    public const ADJUST = 'adjust';

    public const LABELS = [
        self::EARN => 'কেনাকাটার পয়েন্ট',
        self::REDEEM => 'অর্ডারে ব্যবহার',
        self::REFERRAL => 'রেফারেল বোনাস',
        self::ADJUST => 'অ্যাডমিন সমন্বয়',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

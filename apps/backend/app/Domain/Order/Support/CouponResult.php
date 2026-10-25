<?php

namespace App\Domain\Order\Support;

use App\Domain\Customer\Models\Customer;
use App\Domain\Marketing\Models\Coupon;
use App\Settings\RewardSettings;

/** Outcome of the coupon code on a quote; `error` is a code the storefront translates. */
final readonly class CouponResult
{
    public const INVALID = 'invalid';

    public const NOT_STARTED = 'not_started';

    public const EXPIRED = 'expired';

    public const USED_UP = 'used_up';

    public const MIN_SUBTOTAL = 'min_subtotal';

    public const NOT_APPLICABLE = 'not_applicable';

    public const PHONE_LIMIT = 'phone_limit';

    /** Referral codes only work on a new customer's first order, and not on your own code. */
    public const REFERRAL_FIRST_ORDER = 'referral_first_order';

    public function __construct(
        public string $code,
        public ?Coupon $coupon = null,
        public ?string $error = null,
        public int $discount = 0,
        public bool $freeShipping = false,
        /** Set when the code is a customer's referral code rather than a coupon. */
        public ?Customer $referrer = null,
    ) {}

    public function applied(): bool
    {
        return $this->error === null && ($this->coupon !== null || $this->referrer !== null);
    }

    /** Staff/customer-facing Bangla message for API validation errors. */
    public function message(): string
    {
        return match ($this->error) {
            self::NOT_STARTED => 'এই কুপন এখনো চালু হয়নি।',
            self::EXPIRED => 'এই কুপনের মেয়াদ শেষ।',
            self::USED_UP => 'এই কুপনের ব্যবহার সীমা শেষ।',
            self::MIN_SUBTOTAL => 'এই কুপনের জন্য কমপক্ষে ৳'.number_format(($this->coupon?->min_subtotal ?? app(RewardSettings::class)->referee_min_subtotal) / 100).'-এর অর্ডার লাগবে।',
            self::NOT_APPLICABLE => 'কার্টের পণ্যে এই কুপন প্রযোজ্য নয়।',
            self::PHONE_LIMIT => 'এই নম্বরে কুপনটি আগেই ব্যবহার হয়েছে।',
            self::REFERRAL_FIRST_ORDER => 'রেফারেল কোড শুধু নতুন কাস্টমারের প্রথম অর্ডারে চলে।',
            default => 'কুপন কোডটি সঠিক নয়।',
        };
    }
}

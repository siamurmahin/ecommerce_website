<?php

namespace App\Settings;

use App\Support\Phone;
use Spatie\LaravelSettings\Settings;

/** SMS gateway and message templates, edited under সেটিংস → SMS. */
class SmsSettings extends Settings
{
    /** Template keys: customer messages, then the admin alert. */
    public const EVENTS = [
        'order_placed' => 'অর্ডার পাওয়া গেছে (কাস্টমার)',
        'confirmed' => 'অর্ডার কনফার্মড',
        'shipped' => 'কুরিয়ারে পাঠানো হয়েছে',
        'delivered' => 'ডেলিভারড',
        'cancelled' => 'বাতিল',
        'admin_new_order' => 'নতুন অর্ডার (অ্যাডমিনকে)',
    ];

    public const PLACEHOLDERS = ['{name}', '{order}', '{total}', '{phone}', '{shop}', '{hotline}', '{track_url}'];

    public bool $enabled;

    /** Key from config('sms.gateways'). */
    public string $gateway;

    public ?string $api_key;

    /** Sender ID / masking name (SID for SSL Wireless). */
    public ?string $sender_id;

    /** Comma-separated BD mobiles that get the new-order alert. */
    public ?string $admin_phones;

    // Event key => ['enabled' => bool, 'body' => string]. No docblock: spatie parses it as a cast.
    public array $templates;

    /** Login code message; {code} and {shop}. Sent even when order SMS are switched off. */
    public ?string $otp_template;

    /** Abandoned-checkout reminder (see `carts:remind`). Goes only while SMS is on. */
    public bool $recovery_enabled;

    /** Minutes after the customer's last checkout activity before the reminder goes out. */
    public int $recovery_delay_minutes;

    /** {name}, {link}, {coupon}, {shop}, {hotline}. */
    public ?string $recovery_template;

    /** Optional coupon code offered in the reminder; becomes {coupon}. */
    public ?string $recovery_coupon;

    public const RECOVERY_PLACEHOLDERS = ['{name}', '{link}', '{coupon}', '{shop}', '{hotline}'];

    public static function group(): string
    {
        return 'sms';
    }

    public static function encrypted(): array
    {
        return ['api_key'];
    }

    /** @return list<string> */
    public function adminPhones(): array
    {
        return collect(preg_split('/[\s,]+/', (string) $this->admin_phones))
            ->map(fn (string $p) => Phone::normalize($p))
            ->filter(fn (?string $p) => $p && preg_match(Phone::PATTERN, $p))
            ->unique()->values()->all();
    }
}

<?php

namespace App\Domain\Order\Actions;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Enums\IncompleteStatus;
use App\Domain\Order\Models\IncompleteOrder;
use App\Domain\Order\Models\Order;
use App\Domain\Sms\Models\SmsLog;
use App\Domain\Sms\SmsSender;
use App\Settings\BrandSettings;
use App\Settings\SiteSettings;
use App\Settings\SmsSettings;
use Illuminate\Support\Str;

/**
 * One SMS to shoppers who filled in their phone at checkout but didn't order, with a link that
 * puts the same cart back. Never at night, never twice a week to the same phone.
 */
final class SendCartReminders
{
    public const EVENT = 'cart_recovery';

    /** Local hours (shop timezone) messages may go out: 9:00–20:59. */
    private const SEND_FROM = 9;

    private const SEND_UNTIL = 21;

    public function __construct(private SmsSettings $settings, private SmsSender $sms) {}

    public function enabled(): bool
    {
        return $this->settings->enabled && $this->settings->recovery_enabled && filled($this->settings->recovery_template);
    }

    /** @return int messages queued */
    public function handle(): int
    {
        $hour = (int) now(config('shop.timezone'))->format('G');
        if (! $this->enabled() || $hour < self::SEND_FROM || $hour >= self::SEND_UNTIL) {
            return 0;
        }

        $delay = max(15, $this->settings->recovery_delay_minutes);
        $sent = 0;

        IncompleteOrder::query()
            ->where('status', IncompleteStatus::Open)
            ->whereNull('recovery_sms_at')
            ->whereNotNull('items')
            ->whereBetween('last_activity_at', [now()->subDay(), now()->subMinutes($delay)])
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (IncompleteOrder $draft) use (&$sent) {
                // Mark first, so a failure below can't lead to a second message later.
                $draft->update(['recovery_sms_at' => now()]);

                if ($this->shouldSkip($draft)) {
                    return;
                }

                $draft->update(['recovery_token' => $draft->recovery_token ?? Str::random(32)]);
                $this->sms->queue($draft->customer_phone, $this->message($draft), self::EVENT);
                $sent++;
            });

        return $sent;
    }

    private function shouldSkip(IncompleteOrder $draft): bool
    {
        $phone = $draft->customer_phone;

        return blank($draft->items)
            || Customer::isBlocked($phone)
            || Order::where('customer_phone', $phone)->where('created_at', '>=', $draft->created_at)->exists()
            || SmsLog::where('to', $phone)->where('event', self::EVENT)->where('created_at', '>=', now()->subDays(7))->exists();
    }

    public function message(IncompleteOrder $draft): string
    {
        $coupon = filled($this->settings->recovery_coupon)
            ? ($draft->locale === 'en' ? 'Code: ' : 'কোড: ').$this->settings->recovery_coupon
            : '';

        $text = strtr((string) $this->settings->recovery_template, [
            '{name}' => Str::of((string) $draft->customer_name)->trim()->before(' ')->toString() ?: ($draft->locale === 'en' ? 'Hi' : 'প্রিয় গ্রাহক'),
            '{link}' => self::link($draft),
            '{coupon}' => $coupon,
            '{shop}' => app(BrandSettings::class)->name,
            '{hotline}' => app(SiteSettings::class)->hotline,
        ]);

        return trim(preg_replace('/ {2,}/', ' ', $text));
    }

    public static function link(IncompleteOrder $draft): string
    {
        return rtrim((string) config('shop.storefront_url'), '/').'/r/'.$draft->recovery_token;
    }
}

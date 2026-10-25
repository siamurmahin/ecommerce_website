<?php

namespace App\Domain\Customer\Support;

use App\Domain\Sms\SmsSender;
use App\Settings\BrandSettings;
use App\Settings\SmsSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * One-time login codes by SMS. Codes live hashed in the cache for five minutes and die after five wrong
 * tries; a phone can ask for a new code once a minute and five times an hour.
 */
final class OtpService
{
    public const TTL = 300;

    public const RESEND_AFTER = 60;

    public const MAX_ATTEMPTS = 5;

    public const PER_HOUR = 5;

    public function __construct(private SmsSender $sms, private SmsSettings $settings) {}

    /**
     * @return int seconds until another code may be requested
     *
     * @throws ValidationException when asked again too soon
     */
    public function send(string $phone): int
    {
        $current = Cache::get($this->key($phone));
        if ($current && ($wait = $current['sent_at'] + self::RESEND_AFTER - time()) > 0) {
            throw $this->tooSoon($wait);
        }

        $limiter = "otp-send:{$phone}";
        if (RateLimiter::tooManyAttempts($limiter, self::PER_HOUR)) {
            throw $this->tooSoon(RateLimiter::availableIn($limiter));
        }
        RateLimiter::hit($limiter, 3600);

        $code = (string) random_int(100000, 999999);
        Cache::put($this->key($phone), ['hash' => $this->hash($phone, $code), 'attempts' => 0, 'sent_at' => time()], self::TTL);

        $template = filled($this->settings->otp_template) ? $this->settings->otp_template : '{code} আপনার {shop} লগইন কোড।';
        $shop = app(BrandSettings::class)->name;
        $this->sms->sendNow(
            $phone,
            strtr($template, ['{code}' => $code, '{shop}' => $shop]),
            'otp',
            // SMS logs are visible to staff; they must not be able to sign in as the customer.
            strtr($template, ['{code}' => '••••••', '{shop}' => $shop]),
        );

        return self::RESEND_AFTER;
    }

    /** True once for a correct, unexpired code; the code is spent either way after too many misses. */
    public function verify(string $phone, string $code): bool
    {
        $key = $this->key($phone);
        $current = Cache::get($key);
        if (! $current) {
            return false;
        }

        if (hash_equals($current['hash'], $this->hash($phone, trim($code)))) {
            Cache::forget($key);

            return true;
        }

        $current['attempts']++;
        $current['attempts'] >= self::MAX_ATTEMPTS
            ? Cache::forget($key)
            : Cache::put($key, $current, max(1, $current['sent_at'] + self::TTL - time()));

        return false;
    }

    private function key(string $phone): string
    {
        return "otp:{$phone}";
    }

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', "{$phone}|{$code}", (string) config('app.key'));
    }

    private function tooSoon(int $seconds): ValidationException
    {
        return ValidationException::withMessages(['phone' => "একটু পরে আবার চেষ্টা করুন ({$seconds} সেকেন্ড)।"])->status(429);
    }
}

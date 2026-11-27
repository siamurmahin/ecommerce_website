<?php

namespace App\Domain\Customer\Support;

use App\Domain\Sms\SmsSender;
use App\Mail\LoginCodeMail;
use App\Settings\BrandSettings;
use App\Settings\SmsSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * One-time login codes by SMS or email. Codes live hashed in the cache for five minutes and die after five wrong
 * tries; a phone or address can ask for a new code once a minute and five times an hour.
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
        $code = $this->issue($phone, 'phone');

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

    /**
     * Emails a code; sent at once (not queued) so a mail failure shows up to the customer.
     *
     * @return int seconds until another code may be requested
     */
    public function sendEmail(string $email): int
    {
        $email = mb_strtolower(trim($email));
        Mail::to($email)->sendNow(new LoginCodeMail($this->issue($this->emailKey($email), 'email'), app()->getLocale()));

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

    public function verifyEmail(string $email, string $code): bool
    {
        return $this->verify($this->emailKey(mb_strtolower(trim($email))), $code);
    }

    /** Stores a fresh code for the identity (a phone, or "email:…") and returns it. */
    private function issue(string $identity, string $field): string
    {
        $current = Cache::get($this->key($identity));
        if ($current && ($wait = $current['sent_at'] + self::RESEND_AFTER - time()) > 0) {
            throw $this->tooSoon($wait, $field);
        }

        $limiter = "otp-send:{$identity}";
        if (RateLimiter::tooManyAttempts($limiter, self::PER_HOUR)) {
            throw $this->tooSoon(RateLimiter::availableIn($limiter), $field);
        }
        RateLimiter::hit($limiter, 3600);

        $code = (string) random_int(100000, 999999);
        Cache::put($this->key($identity), ['hash' => $this->hash($identity, $code), 'attempts' => 0, 'sent_at' => time()], self::TTL);

        return $code;
    }

    private function emailKey(string $email): string
    {
        return "email:{$email}";
    }

    private function key(string $identity): string
    {
        return "otp:{$identity}";
    }

    private function hash(string $identity, string $code): string
    {
        return hash_hmac('sha256', "{$identity}|{$code}", (string) config('app.key'));
    }

    private function tooSoon(int $seconds, string $field): ValidationException
    {
        return ValidationException::withMessages([$field => "একটু পরে আবার চেষ্টা করুন ({$seconds} সেকেন্ড)।"])->status(429);
    }
}

<?php

namespace Tests\Fakes;

use App\Domain\Sms\Contracts\SmsGateway;

/** Keeps sent messages in memory so tests can read login codes. */
final class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{to: string, message: string}> */
    public static array $sent = [];

    public static function label(): string
    {
        return 'fake';
    }

    public function send(string $to, string $message): void
    {
        self::$sent[] = ['to' => $to, 'message' => $message];
    }

    public static function lastCode(): ?string
    {
        $last = end(self::$sent);

        return $last && preg_match('/\d{6}/', $last['message'], $m) ? $m[0] : null;
    }
}

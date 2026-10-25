<?php

namespace App\Domain\Sms\Drivers;

use App\Domain\Sms\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/** Writes messages to the Laravel log instead of sending them. For local development and testing. */
final class LogGateway implements SmsGateway
{
    public static function label(): string
    {
        return 'টেস্ট মোড (শুধু লগে লিখবে, SMS যাবে না)';
    }

    public function send(string $to, string $message): void
    {
        Log::info('SMS (log gateway)', ['to' => $to, 'message' => $message]);
    }
}

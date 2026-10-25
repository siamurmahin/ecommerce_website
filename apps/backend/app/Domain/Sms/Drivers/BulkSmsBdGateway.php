<?php

namespace App\Domain\Sms\Drivers;

use App\Domain\Sms\Contracts\SmsGateway;
use App\Settings\SmsSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** bulksmsbd.net HTTP API. Success is response code 202. */
final class BulkSmsBdGateway implements SmsGateway
{
    public function __construct(private SmsSettings $settings) {}

    public static function label(): string
    {
        return 'BulkSMSBD (bulksmsbd.net)';
    }

    public function send(string $to, string $message): void
    {
        $response = Http::timeout(10)->asForm()->post('https://bulksmsbd.net/api/smsapi', [
            'api_key' => $this->settings->api_key,
            'senderid' => $this->settings->sender_id,
            'number' => '88'.$to,
            'message' => $message,
            'type' => 'text',
        ]);

        $code = (int) $response->json('response_code');
        if (! $response->successful() || $code !== 202) {
            throw new RuntimeException('BulkSMSBD: '.($response->json('error_message') ?: "code {$code}"));
        }
    }
}

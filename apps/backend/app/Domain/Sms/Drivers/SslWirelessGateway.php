<?php

namespace App\Domain\Sms\Drivers;

use App\Domain\Sms\Contracts\SmsGateway;
use App\Settings\SmsSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** SSL Wireless ISMS Plus (v3). The sender id field holds the SID. */
final class SslWirelessGateway implements SmsGateway
{
    public function __construct(private SmsSettings $settings) {}

    public static function label(): string
    {
        return 'SSL Wireless (ISMS Plus)';
    }

    public function send(string $to, string $message): void
    {
        $response = Http::timeout(10)->acceptJson()->post('https://smsplus.sslwireless.com/api/v3/send-sms', [
            'api_token' => $this->settings->api_key,
            'sid' => $this->settings->sender_id,
            'msisdn' => '88'.$to,
            'sms' => $message,
            'csms_id' => Str::limit(Str::uuid()->getHex()->toString(), 20, ''),
        ]);

        if (! $response->successful() || $response->json('status') !== 'SUCCESS') {
            throw new RuntimeException('SSL Wireless: '.($response->json('error_message') ?: $response->status()));
        }
    }
}

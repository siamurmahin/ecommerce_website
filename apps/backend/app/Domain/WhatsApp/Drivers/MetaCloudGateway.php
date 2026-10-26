<?php

namespace App\Domain\WhatsApp\Drivers;

use App\Domain\WhatsApp\Contracts\WhatsAppGateway;
use App\Settings\WhatsAppSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Meta's WhatsApp Business Cloud API: template messages from the shop's business number. */
final class MetaCloudGateway implements WhatsAppGateway
{
    private const API = 'https://graph.facebook.com/v21.0';

    public function __construct(private WhatsAppSettings $settings) {}

    public static function label(): string
    {
        return 'WhatsApp Business Cloud API (Meta)';
    }

    public function send(string $to, string $template, string $language, array $params): void
    {
        if (blank($this->settings->phone_number_id) || blank($this->settings->access_token)) {
            throw new RuntimeException('WhatsApp: Phone number ID আর access token দিন।');
        }

        $response = Http::timeout(15)->withToken($this->settings->access_token)
            ->post(self::API.'/'.$this->settings->phone_number_id.'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => '88'.$to,
                'type' => 'template',
                'template' => array_filter([
                    'name' => $template,
                    'language' => ['code' => $language],
                    'components' => $params === [] ? null : [[
                        'type' => 'body',
                        'parameters' => array_map(fn (string $p) => ['type' => 'text', 'text' => $p], $params),
                    ]],
                ]),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('WhatsApp: '.($response->json('error.message') ?: 'HTTP '.$response->status()));
        }
    }
}

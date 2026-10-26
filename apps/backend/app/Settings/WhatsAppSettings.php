<?php

namespace App\Settings;

use App\Support\Phone;
use Spatie\LaravelSettings\Settings;

/**
 * WhatsApp order updates, edited under সেটিংস → WhatsApp. Business-initiated WhatsApp messages must use a
 * template approved in Meta Business Manager; each event names that template, and the placeholders in its
 * preview text become the template's {{1}}, {{2}}… in the order they appear.
 */
class WhatsAppSettings extends Settings
{
    /** Same order events as SMS. */
    public const EVENTS = SmsSettings::EVENTS;

    public bool $enabled;

    /** Key from config('whatsapp.gateways'). */
    public string $gateway;

    public ?string $phone_number_id;

    public ?string $access_token;

    /** Comma-separated BD mobiles that get the new-order alert. */
    public ?string $admin_phones;

    // Event key => ['enabled' => bool, 'template' => string, 'language' => string, 'body' => string].
    public array $templates;

    public static function group(): string
    {
        return 'whatsapp';
    }

    public static function encrypted(): array
    {
        return ['access_token'];
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

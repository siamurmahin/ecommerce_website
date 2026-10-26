<?php

namespace App\Domain\WhatsApp\Contracts;

/** A WhatsApp provider. Add one by adding a class and listing it in `config/whatsapp.php`. */
interface WhatsAppGateway
{
    /** Shown in the admin gateway picker. */
    public static function label(): string;

    /**
     * Sends an approved message template.
     *
     * @param  string  $to  BD mobile, 01XXXXXXXXX
     * @param  list<string>  $params  values for the template's {{1}}, {{2}}… in order
     *
     * @throws \RuntimeException when the provider rejects the message
     */
    public function send(string $to, string $template, string $language, array $params): void;
}

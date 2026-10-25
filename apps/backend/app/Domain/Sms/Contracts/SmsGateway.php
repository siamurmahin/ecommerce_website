<?php

namespace App\Domain\Sms\Contracts;

/** An SMS provider. Add one by adding a class and listing it in `config/sms.php`. */
interface SmsGateway
{
    /** Shown in the admin gateway picker. */
    public static function label(): string;

    /**
     * @param  string  $to  BD mobile, 01XXXXXXXXX
     *
     * @throws \RuntimeException when the provider rejects the message
     */
    public function send(string $to, string $message): void;
}

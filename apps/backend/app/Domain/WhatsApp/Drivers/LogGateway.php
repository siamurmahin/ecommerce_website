<?php

namespace App\Domain\WhatsApp\Drivers;

use App\Domain\WhatsApp\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Log;

/** Writes messages to the Laravel log instead of sending them. For local development and testing. */
final class LogGateway implements WhatsAppGateway
{
    public static function label(): string
    {
        return 'টেস্ট মোড (শুধু লগে লিখবে, WhatsApp যাবে না)';
    }

    public function send(string $to, string $template, string $language, array $params): void
    {
        Log::info('WhatsApp (log gateway)', ['to' => $to, 'template' => $template, 'language' => $language, 'params' => $params]);
    }
}

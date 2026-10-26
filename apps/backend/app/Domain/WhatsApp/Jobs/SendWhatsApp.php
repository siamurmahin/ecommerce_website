<?php

namespace App\Domain\WhatsApp\Jobs;

use App\Domain\WhatsApp\Models\WhatsAppMessage;
use App\Domain\WhatsApp\WhatsAppSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $messageId) {}

    public function handle(WhatsAppSender $sender): void
    {
        $message = WhatsAppMessage::find($this->messageId);
        if (! $message || $message->status === 'sent') {
            return;
        }

        $sender->gateway()->send($message->to, $message->template, $message->language, $message->params);
        $message->update(['status' => 'sent', 'error' => null]);
    }

    public function failed(Throwable $e): void
    {
        WhatsAppMessage::whereKey($this->messageId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
    }
}

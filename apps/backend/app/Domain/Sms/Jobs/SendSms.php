<?php

namespace App\Domain\Sms\Jobs;

use App\Domain\Sms\Models\SmsLog;
use App\Domain\Sms\SmsSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $logId) {}

    public function handle(SmsSender $sender): void
    {
        $log = SmsLog::find($this->logId);
        if (! $log || $log->status === 'sent') {
            return;
        }

        $sender->gateway()->send($log->to, $log->message);
        $log->update(['status' => 'sent', 'error' => null]);
    }

    public function failed(Throwable $e): void
    {
        SmsLog::whereKey($this->logId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
    }
}

<?php

namespace App\Domain\Fraud\Jobs;

use App\Domain\Fraud\FraudService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CheckPhone implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $phone) {}

    public function handle(FraudService $fraud): void
    {
        try {
            $fraud->check($this->phone);
        } catch (RuntimeException $e) {
            Log::warning('Fraud check failed', ['error' => $e->getMessage()]);
        }
    }
}

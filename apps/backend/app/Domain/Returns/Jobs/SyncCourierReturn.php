<?php

namespace App\Domain\Returns\Jobs;

use App\Domain\Courier\CourierException;
use App\Domain\Returns\Actions\RequestCourierReturn;
use App\Domain\Returns\Models\OrderReturn;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Poll the courier for a return pickup request's status. */
class SyncCourierReturn implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $returnId) {}

    public function handle(RequestCourierReturn $returns): void
    {
        $return = OrderReturn::with('order')->find($this->returnId);
        if (! $return) {
            return;
        }

        try {
            $returns->refresh($return);
        } catch (CourierException $e) {
            Log::warning('Courier return sync failed', ['return' => $return->id, 'error' => $e->getMessage()]);
        }
    }
}

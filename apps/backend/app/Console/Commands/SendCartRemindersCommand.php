<?php

namespace App\Console\Commands;

use App\Domain\Order\Actions\SendCartReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('carts:remind')]
#[Description('SMS shoppers who left checkout without ordering (admin → SMS সেটিংস → কার্ট রিকভারি)')]
class SendCartRemindersCommand extends Command
{
    public function handle(SendCartReminders $reminders): int
    {
        $this->info($reminders->handle().' reminder(s) queued.');

        return self::SUCCESS;
    }
}

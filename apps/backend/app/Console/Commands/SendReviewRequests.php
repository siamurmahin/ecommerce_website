<?php

namespace App\Console\Commands;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Mail\ReviewRequestMail;
use App\Settings\ReviewRequestSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('reviews:request')]
#[Description('Email customers who got their order a few days ago and ask for a review')]
class SendReviewRequests extends Command
{
    public function handle(ReviewRequestSettings $settings): void
    {
        if (! $settings->enabled) {
            return;
        }

        $count = 0;
        // Delivered at least `delay_days` ago, but not so long ago that the email would come out of nowhere.
        Order::query()
            ->where('status', OrderStatus::Delivered)
            ->whereNotNull('customer_email')->where('customer_email', '!=', '')
            ->whereNull('review_requested_at')
            ->whereHas('statusHistories', fn ($q) => $q->where('to', OrderStatus::Delivered->value)
                ->whereBetween('created_at', [now()->subDays($settings->delay_days + 30), now()->subDays($settings->delay_days)]))
            ->each(function (Order $order) use (&$count) {
                Mail::to($order->customer_email, $order->customer_name)->queue(new ReviewRequestMail($order));
                $order->forceFill(['review_requested_at' => now()])->saveQuietly();
                $count++;
            });

        $this->info("{$count} review request(s) queued.");
    }
}

<?php

namespace App\Mail;

use App\Domain\Order\Models\Order;
use App\Domain\Order\Support\OrderPlaceholders;
use App\Settings\BrandSettings;
use App\Settings\ReviewRequestSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Asks a customer to review what they bought; the button opens the reviews tab of their account. */
class ReviewRequestMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: strtr(app(ReviewRequestSettings::class)->subject, OrderPlaceholders::for($this->order)));
    }

    public function content(): Content
    {
        $order = $this->order->loadMissing('items');

        return new Content(view: 'mail.review-request', with: [
            'body' => strtr(app(ReviewRequestSettings::class)->body, OrderPlaceholders::for($order)),
            'items' => $order->items,
            'shop' => app(BrandSettings::class)->name,
            'url' => rtrim((string) config('shop.storefront_url'), '/').'/account?tab=reviews',
        ]);
    }
}

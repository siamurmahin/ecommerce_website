<?php

namespace App\Mail;

use App\Domain\Order\Models\Order;
use App\Filament\Resources\Orders\OrderResource;
use App\Settings\BrandSettings;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells the shop a new website order came in, with what to pack and a link to it in admin. */
class NewOrderMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "নতুন অর্ডার {$this->order->number} · ".Money::format($this->order->total)." · {$this->order->customer_name}");
    }

    public function content(): Content
    {
        $order = $this->order->loadMissing('items');

        return new Content(view: 'mail.new-order', with: [
            'order' => $order,
            'shop' => app(BrandSettings::class)->name,
            'address' => collect([$order->address, $order->thana_name, $order->district_name])->filter()->unique()->implode(', '),
            'url' => OrderResource::getUrl('view', ['record' => $order], panel: 'admin'),
        ]);
    }
}

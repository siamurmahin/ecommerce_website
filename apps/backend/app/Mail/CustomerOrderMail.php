<?php

namespace App\Mail;

use App\Domain\Order\Models\Order;
use App\Settings\BrandSettings;
use App\Settings\NotificationSettings;
use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Keeps the customer posted about their order: received, confirmed, handed to the courier, delivered, cancelled.
 * Written in the language they ordered in, with the order's items, totals and a link to its page.
 */
class CustomerOrderMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /** @var array<string, array<string, array{subject: string, heading: string, body: string}>> locale => event => copy */
    private const COPY = [
        'bn' => [
            'placed' => ['subject' => 'আপনার অর্ডার {order} পেয়েছি', 'heading' => 'ধন্যবাদ, {name}!', 'body' => 'আপনার অর্ডার আমরা পেয়েছি। শিগগিরই ফোন করে কনফার্ম করব।'],
            'confirmed' => ['subject' => 'অর্ডার {order} কনফার্ম হয়েছে', 'heading' => 'অর্ডার কনফার্ম হয়েছে', 'body' => 'আপনার অর্ডার কনফার্ম হয়েছে, এখন প্যাক করা হচ্ছে।'],
            'shipped' => ['subject' => 'অর্ডার {order} কুরিয়ারে দেওয়া হয়েছে', 'heading' => 'আপনার পার্সেল রওনা হয়েছে', 'body' => 'আপনার অর্ডার কুরিয়ারে দেওয়া হয়েছে। ডেলিভারির সময় কুরিয়ার আপনাকে ফোন করবে।'],
            'delivered' => ['subject' => 'অর্ডার {order} ডেলিভারি হয়েছে', 'heading' => 'ডেলিভারি সম্পন্ন', 'body' => 'আপনার অর্ডার পৌঁছে গেছে। আমাদের থেকে কেনার জন্য ধন্যবাদ!'],
            'cancelled' => ['subject' => 'অর্ডার {order} বাতিল হয়েছে', 'heading' => 'অর্ডার বাতিল হয়েছে', 'body' => 'আপনার অর্ডার বাতিল করা হয়েছে। ভুল হয়ে থাকলে আমাদের সাথে যোগাযোগ করুন।'],
        ],
        'en' => [
            'placed' => ['subject' => 'We received your order {order}', 'heading' => 'Thank you, {name}!', 'body' => "We've received your order and will call you shortly to confirm it."],
            'confirmed' => ['subject' => 'Order {order} is confirmed', 'heading' => 'Your order is confirmed', 'body' => "Your order is confirmed and we're packing it now."],
            'shipped' => ['subject' => 'Order {order} is on its way', 'heading' => 'Your parcel is on its way', 'body' => 'Your order is with the courier. They will call you when they deliver it.'],
            'delivered' => ['subject' => 'Order {order} was delivered', 'heading' => 'Delivered', 'body' => 'Your order has arrived. Thank you for shopping with us!'],
            'cancelled' => ['subject' => 'Order {order} was cancelled', 'heading' => 'Your order was cancelled', 'body' => 'Your order has been cancelled. If this is a mistake, please get in touch.'],
        ],
    ];

    /** @var array<string, array<string, string>> */
    private const LABELS = [
        'bn' => ['order' => 'অর্ডার', 'subtotal' => 'পণ্যের দাম', 'delivery' => 'ডেলিভারি', 'discount' => 'ছাড়', 'gift_wrap' => 'গিফট র‍্যাপ', 'total' => 'মোট', 'payment' => 'পেমেন্ট', 'address' => 'ডেলিভারির ঠিকানা', 'courier' => 'কুরিয়ার', 'tracking' => 'ট্র্যাকিং নম্বর', 'button' => 'অর্ডার দেখুন', 'help' => 'কোনো প্রশ্ন থাকলে কল করুন'],
        'en' => ['order' => 'Order', 'subtotal' => 'Subtotal', 'delivery' => 'Delivery', 'discount' => 'Discount', 'gift_wrap' => 'Gift wrap', 'total' => 'Total', 'payment' => 'Payment', 'address' => 'Delivery address', 'courier' => 'Courier', 'tracking' => 'Tracking number', 'button' => 'View your order', 'help' => 'Questions? Call us at'],
    ];

    public function __construct(public Order $order, public string $event) {}

    public function envelope(): Envelope
    {
        $replyTo = app(NotificationSettings::class)->reply_to;

        return new Envelope(
            subject: $this->fill($this->copy()['subject']),
            replyTo: $replyTo ? [new Address($replyTo, app(BrandSettings::class)->name)] : [],
        );
    }

    public function content(): Content
    {
        $order = $this->order->loadMissing(['items', 'latestShipment']);
        $shipment = $this->event === 'shipped' ? $order->latestShipment : null;
        $lang = $this->lang();
        $base = rtrim((string) config('shop.storefront_url'), '/').($lang === 'en' ? '/en' : '');

        return new Content(view: 'mail.customer-order', with: [
            'order' => $order,
            'lang' => $lang,
            'copy' => array_map($this->fill(...), $this->copy()),
            'label' => self::LABELS[$lang],
            'shop' => app(BrandSettings::class)->name,
            'hotline' => app(SiteSettings::class)->hotline,
            'address' => collect([$order->address, $order->thana_name, $order->district_name])->filter()->unique()->implode(', '),
            'courier' => $shipment?->courierLabel(),
            'tracking' => $shipment ? ($shipment->tracking_code ?: $shipment->consignment_id) : null,
            'payment' => $lang === 'en'
                ? (['cod' => 'Cash on delivery', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket'][$order->payment_method?->value] ?? $order->payment_method?->getLabel())
                : $order->payment_method?->getLabel(),
            'url' => "{$base}/order/{$order->number}?token={$order->access_token}",
        ]);
    }

    private function lang(): string
    {
        return $this->order->locale === 'en' ? 'en' : 'bn';
    }

    /** @return array{subject: string, heading: string, body: string} */
    private function copy(): array
    {
        return self::COPY[$this->lang()][$this->event];
    }

    private function fill(string $text): string
    {
        return strtr($text, ['{order}' => (string) $this->order->number, '{name}' => $this->order->customer_name]);
    }
}

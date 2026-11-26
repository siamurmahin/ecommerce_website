<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Who hears about an order: the shop (email + the admin panel's bell) and the customer (order emails, when they
 * left an address). সেটিংস → নতুন অর্ডারের নোটিফিকেশন.
 */
class NotificationSettings extends Settings
{
    /** Order events a customer can be emailed about; keys match CustomerOrderMail. */
    public const CUSTOMER_EVENTS = ['placed', 'confirmed', 'shipped', 'delivered', 'cancelled'];

    public bool $new_order_email;

    /** Comma/space separated addresses. */
    public ?string $emails;

    public bool $admin_bell;

    public bool $customer_emails;

    /** @var list<string> subset of CUSTOMER_EVENTS */
    public array $customer_email_events;

    /** Shows the optional email field on the checkout page. */
    public bool $ask_email_at_checkout;

    /** Where customers' replies go (the mail is sent from MAIL_FROM_ADDRESS, usually a no-reply address). */
    public ?string $reply_to;

    public function emailsCustomerOn(string $event): bool
    {
        return $this->customer_emails && in_array($event, $this->customer_email_events, true);
    }

    public static function group(): string
    {
        return 'notifications';
    }

    /** @return list<string> */
    public function emailList(): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $this->emails))
            ->map(fn ($e) => strtolower(trim($e)))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()->values()->all();
    }
}

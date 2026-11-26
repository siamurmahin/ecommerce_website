<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Who hears about a new website order: an email to the shop, and the admin panel's bell. সেটিংস → নোটিফিকেশন. */
class NotificationSettings extends Settings
{
    public bool $new_order_email;

    /** Comma/space separated addresses. */
    public ?string $emails;

    public bool $admin_bell;

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

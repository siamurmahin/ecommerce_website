<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** The "how was your order?" email sent after delivery (`reviews:request`), edited under সেটিংস → রিভিউ অনুরোধ. */
class ReviewRequestSettings extends Settings
{
    public const PLACEHOLDERS = ['{name}', '{order}', '{shop}', '{hotline}'];

    public bool $enabled;

    /** Days after delivery before the email goes out. */
    public int $delay_days;

    public string $subject;

    public string $body;

    public static function group(): string
    {
        return 'review_request';
    }
}

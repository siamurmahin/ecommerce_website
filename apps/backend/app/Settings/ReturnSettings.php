<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Customers asking for a return or exchange from their order page; edited under সেটিংস → রিটার্ন ও এক্সচেঞ্জ. */
class ReturnSettings extends Settings
{
    public bool $enabled;

    /** Days after delivery the customer can still ask. */
    public int $window_days;

    public string $policy;

    public string $policy_en;

    public static function group(): string
    {
        return 'returns';
    }
}

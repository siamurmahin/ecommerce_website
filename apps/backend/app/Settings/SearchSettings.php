<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Storefront search tuning, edited under ক্যাটালগ → সার্চ. */
class SearchSettings extends Settings
{
    /** Each entry is one group of words that mean the same thing, comma-separated: "আতর, attar, ator, itr". */
    public array $synonyms;

    /** Shown as quick picks under the search box before the customer types. */
    public array $popular;

    public static function group(): string
    {
        return 'search';
    }
}

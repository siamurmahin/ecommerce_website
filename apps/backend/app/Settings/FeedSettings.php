<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FeedSettings extends Settings
{
    public bool $enabled;

    /** Keep sold-out items in the feed marked "out of stock" (ads pause them) instead of dropping them. */
    public bool $include_out_of_stock;

    /** Only these categories (and their subcategories); empty = every category. @var list<int> */
    public array $category_ids;

    /** Language of titles and descriptions: bn or en. */
    public string $locale;

    /** Used when a product has no brand; Google needs one. */
    public ?string $brand_fallback;

    /** Google product category (ID or full path), applied to every item; optional. */
    public ?string $google_category;

    public static function group(): string
    {
        return 'feeds';
    }
}

<?php

namespace App\Settings;

use Carbon\CarbonImmutable;
use Spatie\LaravelSettings\Settings;

/** Ramadan mode and the prayer-times widget, edited under কনটেন্ট → রমজান ও নামাজের সময়. */
class SeasonalSettings extends Settings
{
    public bool $ramadan_enabled;

    /** Y-m-d in the shop's timezone; Ramadan mode runs from the first to the last day inclusive. */
    public ?string $ramadan_starts_on;

    public ?string $ramadan_ends_on;

    /** Replaces the top-bar announcement while Ramadan mode is on. */
    public ?string $ramadan_announcement;

    public ?string $ramadan_announcement_en;

    /** Default city for prayer times (key of PRAYER_CITIES). */
    public string $prayer_city;

    /** Minutes added to the computed times, to match the local mosque calendar. */
    public int $prayer_offset;

    public const PRAYER_CITIES = [
        'dhaka' => 'ঢাকা', 'chattogram' => 'চট্টগ্রাম', 'sylhet' => 'সিলেট', 'rajshahi' => 'রাজশাহী',
        'khulna' => 'খুলনা', 'barishal' => 'বরিশাল', 'rangpur' => 'রংপুর', 'mymensingh' => 'ময়মনসিংহ',
        'cumilla' => 'কুমিল্লা', 'narayanganj' => 'নারায়ণগঞ্জ', 'gazipur' => 'গাজীপুর', 'bogura' => 'বগুড়া', 'coxs_bazar' => 'কক্সবাজার',
    ];

    public static function group(): string
    {
        return 'seasonal';
    }

    public function ramadanActive(): bool
    {
        if (! $this->ramadan_enabled || blank($this->ramadan_starts_on) || blank($this->ramadan_ends_on)) {
            return false;
        }
        $today = CarbonImmutable::now(config('shop.timezone'))->toDateString();

        return $today >= $this->ramadan_starts_on && $today <= $this->ramadan_ends_on;
    }

    public function announcement(): ?string
    {
        return app()->getLocale() === 'en' && filled($this->ramadan_announcement_en) ? $this->ramadan_announcement_en : $this->ramadan_announcement;
    }
}

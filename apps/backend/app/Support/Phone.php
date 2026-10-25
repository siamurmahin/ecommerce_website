<?php

namespace App\Support;

/** Bangladeshi mobile numbers, stored as 11 digits: 01XXXXXXXXX. */
final class Phone
{
    public const PATTERN = '/^01[3-9]\d{8}$/';

    /** Accepts Bangla digits, spaces/dashes and a +88 / 88 prefix. */
    public static function normalize(?string $input): string
    {
        $digits = preg_replace('/\D/', '', self::latinDigits((string) $input));

        return str_starts_with($digits, '880') ? substr($digits, 2) : $digits;
    }

    /** ০-৯ → 0-9, leaving everything else as typed. */
    public static function latinDigits(string $input): string
    {
        return strtr($input, array_combine(
            ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        ));
    }
}

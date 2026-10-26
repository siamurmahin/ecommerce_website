<?php

namespace App\Support;

/**
 * Money is stored as integer poisha (1 ৳ = 100 poisha) and shown in taka.
 */
final class Money
{
    public static function toPoisha(int|float|string|null $taka): ?int
    {
        if ($taka === null || $taka === '') {
            return null;
        }

        return (int) round(((float) $taka) * 100);
    }

    public static function toTaka(?int $poisha): ?float
    {
        return $poisha === null ? null : $poisha / 100;
    }

    public static function format(?int $poisha): string
    {
        if ($poisha === null) {
            return '';
        }

        return ($poisha < 0 ? '-' : '').'৳'.number_format(abs($poisha) / 100, $poisha % 100 === 0 ? 0 : 2);
    }
}

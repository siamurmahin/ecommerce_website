<?php

namespace App\Domain\Report;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/** A span of whole days in the shop's timezone, queried against UTC timestamps. */
final class DateRange
{
    public const PRESETS = [
        'today' => 'আজ',
        'yesterday' => 'গতকাল',
        '7d' => 'শেষ ৭ দিন',
        '30d' => 'শেষ ৩০ দিন',
        'this_month' => 'এই মাস',
        'last_month' => 'গত মাস',
        'custom' => 'নির্দিষ্ট তারিখ',
    ];

    private function __construct(public readonly CarbonImmutable $start, public readonly CarbonImmutable $end) {}

    public static function timezone(): string
    {
        return config('shop.timezone', 'Asia/Dhaka');
    }

    /** From a filter form: a preset key, or 'custom' with Y-m-d dates. Unknown input falls back to 30 days. */
    public static function from(?string $preset, ?string $from = null, ?string $until = null): self
    {
        $today = CarbonImmutable::now(self::timezone())->startOfDay();

        [$start, $end] = match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            '7d' => [$today->subDays(6), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'custom' => [
                $from ? CarbonImmutable::parse($from, self::timezone()) : $today->subDays(29),
                $until ? CarbonImmutable::parse($until, self::timezone()) : $today,
            ],
            default => [$today->subDays(29), $today],
        };

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        return new self($start->startOfDay(), $end->endOfDay());
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} UTC bounds for whereBetween */
    public function utc(): array
    {
        return [$this->start->utc(), $this->end->utc()];
    }

    /** @return list<string> every day in the range as Y-m-d */
    public function days(): array
    {
        return array_map(fn ($d) => $d->format('Y-m-d'), iterator_to_array(CarbonPeriod::create($this->start, '1 day', $this->end->startOfDay())));
    }

    /** The same number of days right before this range, for "vs previous period". */
    public function previous(): self
    {
        $days = count($this->days());

        return new self($this->start->subDays($days), $this->start->subDay()->endOfDay());
    }

    public function label(): string
    {
        return $this->start->isSameDay($this->end)
            ? $this->start->format('d M Y')
            : $this->start->format('d M').' – '.$this->end->format('d M Y');
    }

    /** SQL for a UTC timestamp column as a local Y-m-d date (Bangladesh has no DST, so a fixed offset is exact). */
    public static function localDateSql(string $column, string $driver): string
    {
        $offset = CarbonImmutable::now(self::timezone())->format('P'); // +06:00

        return match ($driver) {
            'sqlite' => "date(datetime({$column}, '".self::sqliteModifier($offset)."'))",
            'pgsql' => "date(({$column} at time zone 'UTC') at time zone '".self::timezone()."')",
            default => "date(convert_tz({$column}, '+00:00', '{$offset}'))",
        };
    }

    private static function sqliteModifier(string $offset): string
    {
        [$h, $m] = explode(':', ltrim($offset, '+-'));
        $sign = str_starts_with($offset, '-') ? '-' : '+';

        return sprintf('%s%d minutes', $sign, ((int) $h) * 60 + (int) $m);
    }
}

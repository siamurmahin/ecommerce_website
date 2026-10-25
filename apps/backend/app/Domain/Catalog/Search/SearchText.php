<?php

namespace App\Domain\Catalog\Search;

use App\Settings\SearchSettings;
use Normalizer;

/** Turns what a shopper typed into comparable words, with each word's synonyms from Admin → সার্চ. */
class SearchText
{
    private const BN_DIGITS = ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];

    public function __construct(private SearchSettings $settings) {}

    /** NFC, lower case, Latin digits, punctuation to spaces. */
    public static function normalize(string $text): string
    {
        $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;
        $text = strtr(mb_strtolower($text), self::BN_DIGITS);
        $text = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Words of the term, each with the spellings that count as a match. Multi-word synonyms
     * ("prayer mat") are kept together as one word.
     *
     * @return list<list<string>>
     */
    public function words(string $term): array
    {
        $term = self::normalize($term);
        if ($term === '') {
            return [];
        }

        $groups = $this->groups();
        $words = [];

        // Phrases first, so "prayer mat" isn't split into "prayer" and "mat".
        foreach ($groups as $group) {
            foreach ($group as $synonym) {
                if (str_contains($synonym, ' ') && preg_match('/(^| )'.preg_quote($synonym, '/').'( |$)/u', $term)) {
                    $words[] = $group;
                    $term = trim(preg_replace('/(^| )'.preg_quote($synonym, '/').'( |$)/u', ' ', $term) ?? '');
                    break;
                }
            }
        }

        foreach (array_slice(array_filter(explode(' ', $term)), 0, 6) as $word) {
            $group = collect($groups)->first(fn (array $g) => in_array($word, $g, true));
            $words[] = $group ? array_values(array_unique([$word, ...$group])) : [$word];
        }

        return $words;
    }

    /** @return list<list<string>> */
    private function groups(): array
    {
        return once(fn () => collect($this->settings->synonyms)
            ->map(fn ($line) => collect(explode(',', (string) $line))->map(fn ($w) => self::normalize($w))->filter()->unique()->values()->all())
            ->filter(fn (array $g) => count($g) > 1)
            ->values()->all());
    }
}

<?php

namespace App\Domain\Catalog\Search\Drivers;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Search\Contracts\SearchEngine;
use App\Domain\Catalog\Search\SearchHits;
use App\Domain\Catalog\Search\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Search that runs on the shop's own database: every word must match (with synonyms), results
 * ranked by where they matched; when nothing matches it tries the closest spelling of each word
 * from product and category names. Fine for a few thousand products.
 */
class DatabaseSearch implements SearchEngine
{
    public function __construct(private SearchText $text) {}

    public function search(string $term, int $limit = 500): SearchHits
    {
        $words = $this->text->words($term);
        if ($words === []) {
            return new SearchHits([]);
        }

        $ids = $this->match($words, $limit);
        if ($ids !== []) {
            return new SearchHits($ids);
        }

        $corrected = $this->correct($words);
        if ($corrected === null) {
            return new SearchHits([]);
        }

        return new SearchHits($this->match($this->text->words($corrected), $limit), $corrected);
    }

    /**
     * @param  list<list<string>>  $words
     * @return list<int>
     */
    private function match(array $words, int $limit): array
    {
        $query = Product::visible()->with(['category:id,name,name_en', 'brand:id,name'])
            ->select(['id', 'name', 'name_en', 'sku', 'short_description', 'short_description_en', 'category_id', 'brand_id', 'sold_count']);

        foreach ($words as $variants) {
            $query->where(function (Builder $q) use ($variants) {
                foreach ($variants as $v) {
                    $like = '%'.addcslashes($v, '%_\\').'%';
                    $q->orWhere('name', 'like', $like)->orWhere('name_en', 'like', $like)->orWhere('sku', $v)
                        ->orWhere('short_description', 'like', $like)->orWhere('short_description_en', 'like', $like)
                        ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like)->orWhere('name_en', 'like', $like))
                        ->orWhereHas('brand', fn (Builder $b) => $b->where('name', 'like', $like));
                }
            });
        }

        return $query->limit($limit)->get()
            ->map(fn (Product $p) => [$p->id, $this->score($p, $words)])
            ->sortByDesc(fn (array $row) => $row[1])
            ->map(fn (array $row) => $row[0])
            ->values()->all();
    }

    /** Name hits beat category/description hits; a name that starts with the word beats one that contains it. */
    private function score(Product $product, array $words): float
    {
        $name = SearchText::normalize($product->name.' '.$product->name_en);
        $other = SearchText::normalize(implode(' ', [$product->category?->name, $product->category?->name_en, $product->brand?->name]));
        $score = log(1 + $product->sold_count) / 10;

        foreach ($words as $variants) {
            $best = 0;
            foreach ($variants as $v) {
                $best = max($best, match (true) {
                    str_starts_with($name, $v) => 4,
                    (bool) preg_match('/(^| )'.preg_quote($v, '/').'/u', $name) => 3,
                    str_contains($name, $v) => 2,
                    str_contains($other, $v) => 1.5,
                    default => 1,
                });
            }
            $score += $best;
        }

        return $score;
    }

    /**
     * The term with each unknown word replaced by its closest known word, or null when nothing is close.
     *
     * @param  list<list<string>>  $words
     */
    private function correct(array $words): ?string
    {
        $vocabulary = $this->vocabulary();
        $changed = false;
        $fixed = [];

        foreach ($words as $variants) {
            $word = $variants[0];
            if (mb_strlen($word) < 3 || isset($vocabulary[$word])) {
                $fixed[] = $word;

                continue;
            }

            $max = mb_strlen($word) <= 5 ? 1 : 2;
            $best = null;
            $bestDistance = $max + 1;
            foreach ($vocabulary as $candidate => $_) {
                if (abs(mb_strlen($candidate) - mb_strlen($word)) > $max) {
                    continue;
                }
                $distance = self::distance($word, (string) $candidate);
                if ($distance < $bestDistance) {
                    [$best, $bestDistance] = [(string) $candidate, $distance];
                }
            }

            $fixed[] = $best ?? $word;
            $changed = $changed || $best !== null;
        }

        return $changed ? implode(' ', $fixed) : null;
    }

    /** Words from visible product and category names, as a set. Refreshed every 10 minutes. */
    private function vocabulary(): array
    {
        return Cache::remember('search:vocabulary', 600, function () {
            $names = Product::visible()->get(['name', 'name_en'])->flatMap(fn ($p) => [$p->name, $p->name_en])
                ->merge(Category::active()->get(['name', 'name_en'])->flatMap(fn ($c) => [$c->name, $c->name_en]));

            return $this->words($names)->flip()->map(fn () => true)->all();
        });
    }

    /** @return Collection<int, string> */
    private function words(Collection $names): Collection
    {
        return $names->filter()->flatMap(fn ($n) => explode(' ', SearchText::normalize((string) $n)))
            ->filter(fn ($w) => mb_strlen($w) >= 3)->unique()->values();
    }

    /** Levenshtein distance over characters (PHP's levenshtein() counts bytes, which breaks Bangla). */
    public static function distance(string $a, string $b): int
    {
        $a = mb_str_split($a);
        $b = mb_str_split($b);
        $previous = range(0, count($b));

        foreach ($a as $i => $ca) {
            $current = [$i + 1];
            foreach ($b as $j => $cb) {
                $current[$j + 1] = min($previous[$j + 1] + 1, $current[$j] + 1, $previous[$j] + ($ca === $cb ? 0 : 1));
            }
            $previous = $current;
        }

        return $previous[count($b)];
    }
}

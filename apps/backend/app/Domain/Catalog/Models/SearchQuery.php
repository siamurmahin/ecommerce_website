<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/** What shoppers typed into search, one row per normalized term — shows what they look for and don't find. */
class SearchQuery extends Model
{
    use Prunable;

    protected $fillable = ['term', 'searches', 'results', 'corrected_to', 'last_searched_at'];

    protected function casts(): array
    {
        return ['last_searched_at' => 'datetime', 'searches' => 'integer', 'results' => 'integer'];
    }

    public static function record(string $term, int $results, ?string $correctedTo): void
    {
        $term = mb_substr($term, 0, 100);
        if (mb_strlen($term) < 2) {
            return;
        }

        $row = static::firstOrNew(['term' => $term]);
        $row->fill(['results' => $results, 'corrected_to' => $correctedTo, 'last_searched_at' => now()]);
        $row->searches++;
        $row->save();
    }

    public function prunable(): Builder
    {
        return static::where('last_searched_at', '<', now()->subMonths(6));
    }
}

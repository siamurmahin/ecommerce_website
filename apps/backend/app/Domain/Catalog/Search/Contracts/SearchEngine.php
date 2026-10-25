<?php

namespace App\Domain\Catalog\Search\Contracts;

use App\Domain\Catalog\Search\SearchHits;

interface SearchEngine
{
    /** Visible products matching the term, most relevant first (at most `$limit`). */
    public function search(string $term, int $limit = 500): SearchHits;
}

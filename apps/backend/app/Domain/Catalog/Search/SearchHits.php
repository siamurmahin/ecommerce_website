<?php

namespace App\Domain\Catalog\Search;

/** Matching product ids, best first. `correctedTo` is set when nothing matched as typed and a spelling fix did. */
final readonly class SearchHits
{
    /** @param  list<int>  $ids */
    public function __construct(public array $ids, public ?string $correctedTo = null) {}
}

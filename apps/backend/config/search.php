<?php

use App\Domain\Catalog\Search\Drivers\DatabaseSearch;

return [
    // Product search engine. Add one (e.g. Meilisearch) by adding a class that implements SearchEngine.
    'driver' => env('SEARCH_DRIVER', 'database'),

    'drivers' => [
        'database' => DatabaseSearch::class,
    ],
];

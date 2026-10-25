<?php

namespace App\Filament\Resources\SearchQueries\Pages;

use App\Filament\Resources\SearchQueries\SearchQueryResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSearchQueries extends ListRecords
{
    protected static string $resource = SearchQueryResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('সব'),
            'none' => Tab::make('ফল পাওয়া যায়নি')->modifyQueryUsing(fn (Builder $query) => $query->where('results', 0)),
            'corrected' => Tab::make('বানান ভুল')->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('corrected_to')),
        ];
    }
}

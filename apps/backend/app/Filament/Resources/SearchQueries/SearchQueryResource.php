<?php

namespace App\Filament\Resources\SearchQueries;

use App\Domain\Catalog\Models\SearchQuery;
use App\Filament\Pages\ManageSearchSettings;
use App\Filament\Resources\SearchQueries\Pages\ListSearchQueries;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** What shoppers searched for, read-only. Terms with no results show what to stock or add as a synonym. */
class SearchQueryResource extends Resource
{
    protected static ?string $model = SearchQuery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'রিপোর্ট';

    protected static ?string $navigationLabel = 'কাস্টমাররা কী খুঁজছে';

    protected static ?string $modelLabel = 'সার্চ';

    protected static ?string $pluralModelLabel = 'সার্চ';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('searches', 'desc')
            ->description('গত ৬ মাসে স্টোরফ্রন্টে যা সার্চ হয়েছে। কোনো লেখায় ফল না এলে পণ্যটা যোগ করুন, অথবা সার্চ সেটিংসে সমার্থক শব্দ দিন (যেমন "ator" → আতর)।')
            ->columns([
                TextColumn::make('term')->label('লেখা')->searchable(),
                TextColumn::make('searches')->label('কতবার')->numeric()->sortable(),
                TextColumn::make('results')->label('ফল')->numeric()->sortable()
                    ->color(fn (SearchQuery $record) => $record->results === 0 ? 'danger' : null),
                TextColumn::make('corrected_to')->label('বানান ঠিক করে দেখানো')->placeholder('—'),
                TextColumn::make('last_searched_at')->label('শেষ সার্চ')->since()->sortable(),
            ])
            ->headerActions([
                Action::make('settings')->label('সার্চ সেটিংস')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->url(fn () => ManageSearchSettings::getUrl())
                    ->visible(fn () => ManageSearchSettings::canAccess()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSearchQueries::route('/')];
    }
}

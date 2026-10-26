<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('খরচ যোগ করুন')->mutateDataUsing(fn (array $data) => [...$data, 'user_id' => auth()->id()])];
    }
}

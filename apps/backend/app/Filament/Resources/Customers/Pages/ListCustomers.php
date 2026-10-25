<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        // For blocking a number that has never ordered online; everyone else appears with their first order.
        return [CreateAction::make()->label('নম্বর যোগ করুন')];
    }
}

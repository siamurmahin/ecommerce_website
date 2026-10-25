<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function getTitle(): string
    {
        return $this->record->name ?: $this->record->phone;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('call')->label('কল করুন')->icon(Heroicon::OutlinedPhone)->color('gray')
                ->url(fn () => 'tel:'.$this->record->phone),
            EditAction::make(),
        ];
    }
}

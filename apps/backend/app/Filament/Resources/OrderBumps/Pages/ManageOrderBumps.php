<?php

namespace App\Filament\Resources\OrderBumps\Pages;

use App\Filament\Resources\OrderBumps\OrderBumpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOrderBumps extends ManageRecords
{
    protected static string $resource = OrderBumpResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

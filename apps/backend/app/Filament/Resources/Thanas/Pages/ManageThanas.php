<?php

namespace App\Filament\Resources\Thanas\Pages;

use App\Filament\Resources\Thanas\ThanaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageThanas extends ManageRecords
{
    protected static string $resource = ThanaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

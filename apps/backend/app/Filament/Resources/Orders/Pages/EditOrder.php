<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Shipping\Models\District;
use App\Domain\Shipping\Models\Thana;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['district_id'])) {
            $data['district_name'] = District::find($data['district_id'])?->name;
        }
        if (array_key_exists('thana_id', $data)) {
            $data['thana_name'] = Thana::find($data['thana_id'])?->name;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}

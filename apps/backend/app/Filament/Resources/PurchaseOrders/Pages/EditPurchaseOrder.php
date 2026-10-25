<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    public function getTitle(): string
    {
        return $this->record->number.' — '.$this->record->supplier?->name;
    }

    protected function getHeaderActions(): array
    {
        return array_map(fn ($action) => $action->after(fn () => $this->refreshFormData(['status', 'paid_amount'])), PurchaseOrderResource::workflowActions());
    }

    protected function afterSave(): void
    {
        $this->record->recalculate();
    }
}

<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Courier\Jobs\SyncShipment;
use App\Domain\Customer\Models\Customer;
use App\Filament\Actions\BookCourierAction;
use App\Filament\Actions\ChangeStatusAction;
use App\Filament\Actions\FraudCheckAction;
use App\Filament\Actions\PaymentAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return 'অর্ডার '.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        return [
            BookCourierAction::make()->after(fn () => $this->refreshFormData(['status'])),
            ChangeStatusAction::make()->after(fn () => $this->refreshFormData(['status'])),
            PaymentAction::make()->after(fn () => $this->refreshFormData(['payment_status'])),
            Action::make('syncCourier')->label('কুরিয়ার স্ট্যাটাস আনুন')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible(fn () => $this->record->latestShipment && ! $this->record->latestShipment->status->isFinal())
                ->action(function () {
                    SyncShipment::dispatchSync($this->record->latestShipment->id);
                    $this->record->refresh();
                    $this->refreshFormData(['status']);
                }),
            FraudCheckAction::make(),
            Action::make('invoice')->label('ইনভয়েস প্রিন্ট')->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->url(fn () => route('admin.invoices', ['orders' => $this->record->number]))
                ->openUrlInNewTab(),
            Action::make('call')->label('কল করুন')->icon(Heroicon::OutlinedPhone)->color('gray')
                ->url(fn () => 'tel:'.$this->record->customer_phone),
            Action::make('customer')->label('কাস্টমার প্রোফাইল')->icon(Heroicon::OutlinedUser)->color('gray')
                ->visible(fn () => $this->customer() && CustomerResource::canView($this->customer()))
                ->url(fn () => CustomerResource::getUrl('view', ['record' => $this->customer()])),
            Action::make('block')->label('নম্বর ব্লক')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                ->modalDescription('এই নম্বর থেকে ওয়েবসাইটে আর অর্ডার বা লগইন করা যাবে না। কাস্টমার পেজ থেকে পরে খুলে দেওয়া যায়।')
                ->schema([TextInput::make('reason')->label('কারণ')->placeholder('ফেক অর্ডার')->maxLength(255)])
                ->visible(fn () => ! $this->customer()?->is_blocked && (auth()->user()?->can('update', $this->customer() ?? Customer::class) ?? false))
                ->action(function (array $data) {
                    Customer::updateOrCreate(['phone' => $this->record->customer_phone], ['is_blocked' => true, 'blocked_reason' => $data['reason'] ?? null])
                        ->fill(['name' => $this->customer()?->name ?? $this->record->customer_name])->save();
                    Notification::make()->success()->title('নম্বর ব্লক হয়েছে')->send();
                }),
            EditAction::make(),
        ];
    }

    private function customer(): ?Customer
    {
        return Customer::firstWhere('phone', $this->record->customer_phone);
    }
}

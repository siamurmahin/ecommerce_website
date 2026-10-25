<?php

namespace App\Filament\Resources\DeliveryZones\Pages;

use App\Filament\Resources\DeliveryZones\DeliveryZoneResource;
use App\Filament\Support\Fields;
use App\Settings\ShippingSettings;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageDeliveryZones extends ManageRecords
{
    protected static string $resource = DeliveryZoneResource::class;

    protected function getHeaderActions(): array
    {
        $settings = app(ShippingSettings::class);

        return [
            Action::make('freeDelivery')
                ->label($settings->free_delivery_threshold > 0
                    ? 'ফ্রি ডেলিভারি: '.Money::format($settings->free_delivery_threshold).'+'
                    : 'ফ্রি ডেলিভারি: বন্ধ')
                ->icon(Heroicon::OutlinedGift)
                ->color('gray')
                ->authorize(fn () => auth()->user()->can('Update:DeliveryZone'))
                ->fillForm(['free_delivery_threshold' => $settings->free_delivery_threshold])
                ->schema([
                    Fields::money('free_delivery_threshold', 'এর বেশি অর্ডারে ফ্রি ডেলিভারি')
                        ->helperText('০ দিলে বন্ধ থাকবে। কোনো পণ্যে "ফ্রি ডেলিভারি" চালু থাকলে সেই অর্ডার সবসময় ফ্রি।')
                        ->required(),
                ])
                ->action(function (array $data) use ($settings) {
                    $settings->free_delivery_threshold = (int) $data['free_delivery_threshold'];
                    $settings->save();
                    Notification::make()->title('সেভ হয়েছে')->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}

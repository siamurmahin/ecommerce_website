<?php

namespace App\Filament\Resources\IncompleteOrders\Pages;

use App\Filament\Resources\IncompleteOrders\IncompleteOrderResource;
use App\Settings\ShippingSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageIncompleteOrders extends ManageRecords
{
    protected static string $resource = IncompleteOrderResource::class;

    public function getSubheading(): ?string
    {
        return 'চেকআউটে মোবাইল নম্বর দিয়েও অর্ডার শেষ করেননি যারা। একই নম্বরে অর্ডার হলে নিজে থেকেই "অর্ডার হয়েছে" হয়ে যায়।';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')->label('সেটিংস')->icon(Heroicon::OutlinedCog6Tooth)->color('gray')
                ->authorize(fn () => auth()->user()->can('Update:IncompleteOrder'))
                ->fillForm(fn () => ['capture' => app(ShippingSettings::class)->capture_incomplete_orders])
                ->schema([
                    Toggle::make('capture')->label('চেকআউট থেকে ইনকমপ্লিট অর্ডার সংগ্রহ করো')
                        ->helperText('কাস্টমার সঠিক মোবাইল নম্বর লিখলেই তথ্য সেভ হয়, অর্ডার না দিলেও।'),
                ])
                ->action(function (array $data) {
                    $settings = app(ShippingSettings::class);
                    $settings->capture_incomplete_orders = (bool) $data['capture'];
                    $settings->save();
                    Notification::make()->success()->title('সেভ হয়েছে')->send();
                }),
        ];
    }
}

<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\Order;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('নতুন অর্ডার (ফোন/ফেসবুক)')];
    }

    public function getTabs(): array
    {
        $counts = Order::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'all' => Tab::make('সব'),
            ...collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $status) => [
                $status->value => Tab::make($status->getLabel())
                    ->badge($counts[$status->value] ?? null)
                    ->badgeColor($status->getColor())
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status)),
            ])->all(),
            'payment' => Tab::make('পেমেন্ট যাচাই')
                ->icon(Heroicon::OutlinedBanknotes)
                ->badge(Order::where('payment_status', PaymentStatus::Pending)->count() ?: null)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_status', PaymentStatus::Pending)),
        ];
    }
}

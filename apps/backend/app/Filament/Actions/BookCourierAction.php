<?php

namespace App\Filament\Actions;

use App\Domain\Courier\Actions\BookShipment;
use App\Domain\Courier\CourierException;
use App\Domain\Courier\CourierManager;
use App\Domain\Courier\Data\Parcel;
use App\Domain\Courier\Drivers\RedxCourier;
use App\Domain\Order\Models\Order;
use App\Filament\Support\Fields;
use App\Settings\CourierSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/** "Send to courier" for one order (with COD/area/note) or many (defaults for each). */
final class BookCourierAction
{
    public static function make(): Action
    {
        return Action::make('bookCourier')
            ->label('কুরিয়ারে পাঠান')
            ->icon(Heroicon::OutlinedTruck)
            ->color('primary')
            ->authorize(fn (Order $record) => auth()->user()->can('update', $record))
            ->visible(fn (Order $record) => ! $record->shipments()->active()->exists())
            ->modalDescription(fn () => self::couriers() ? null : 'কোনো কুরিয়ারের API key সেট করা নেই। সেটিংস → কুরিয়ার থেকে যোগ করুন।')
            ->fillForm(fn (Order $record) => [
                'courier' => self::defaultCourier(),
                'cod_amount' => self::codFor($record),
                'weight' => 0.5,
                'note' => $record->customer_note,
                'area_id' => self::guessArea($record),
            ])
            ->schema(fn (Order $record) => [
                Select::make('courier')->label('কুরিয়ার')->options(self::couriers())->required()->native(false)->live(),
                Fields::money('cod_amount', 'কাস্টমারের কাছ থেকে নেবে (COD)')->required()
                    ->helperText('অগ্রিম পেমেন্ট হয়ে থাকলে ০ দিন'),
                Select::make('area_id')->label('ডেলিভারি এরিয়া')->searchable()->required()
                    ->options(fn (Get $get) => self::areas($record, $get('courier')))
                    ->visible(fn (Get $get) => self::areas($record, $get('courier')) !== null),
                TextInput::make('weight')->label('ওজন (কেজি)')->numeric()->minValue(0.1)->maxValue(30)->step(0.1)->required(),
                Textarea::make('note')->label('কুরিয়ারের জন্য নোট')->rows(2)->maxLength(250),
            ])
            ->action(function (Order $record, array $data, BookShipment $book, Action $action) {
                try {
                    $shipment = $book->handle($record, $data['courier'], new Parcel(
                        codAmount: (int) $data['cod_amount'],
                        weightKg: (float) $data['weight'],
                        note: $data['note'] ?? null,
                        areaId: $data['area_id'] ?? null,
                    ), auth()->id());
                } catch (CourierException $e) {
                    Notification::make()->danger()->title('বুক করা যায়নি')->body($e->getMessage())->persistent()->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title("{$shipment->courierLabel()}-এ বুক হয়েছে")->body("কনসাইনমেন্ট: {$shipment->consignment_id}")->send();
            });
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('bookCourier')
            ->label('কুরিয়ারে পাঠান')
            ->icon(Heroicon::OutlinedTruck)
            ->authorize(fn () => auth()->user()->can('Update:Order'))
            ->fillForm(fn () => ['courier' => self::defaultCourier(), 'weight' => 0.5])
            ->schema([
                Select::make('courier')->label('কুরিয়ার')->options(self::couriers())->required()->native(false),
                TextInput::make('weight')->label('প্রতিটার ওজন (কেজি)')->numeric()->minValue(0.1)->maxValue(30)->step(0.1)->required(),
            ])
            ->modalDescription('COD হবে অর্ডারের মোট টাকা (অগ্রিম পেমেন্ট থাকলে ০), নোট হবে কাস্টমারের নোট। আগেই বুক করা অর্ডার বাদ যাবে।')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data, BookShipment $book) {
                $booked = 0;
                $errors = [];

                foreach ($records as $order) {
                    try {
                        $book->handle($order, $data['courier'], new Parcel(
                            codAmount: self::codFor($order),
                            weightKg: (float) $data['weight'],
                            note: $order->customer_note,
                        ), auth()->id());
                        $booked++;
                    } catch (CourierException $e) {
                        $errors[] = $e->getMessage();
                    }
                }

                Notification::make()
                    ->{$errors ? 'warning' : 'success'}()
                    ->title("{$booked}টা বুক হয়েছে".($errors ? ', '.count($errors).'টা হয়নি' : ''))
                    ->body(implode("\n", array_slice($errors, 0, 8)))
                    ->persistent((bool) $errors)
                    ->send();
            });
    }

    /** @return array<string, string> */
    private static function couriers(): array
    {
        return app(CourierManager::class)->configured();
    }

    private static function defaultCourier(): ?string
    {
        $couriers = self::couriers();
        $default = app(CourierSettings::class)->default_courier;

        return array_key_exists((string) $default, $couriers) ? $default : array_key_first($couriers);
    }

    private static function codFor(Order $order): int
    {
        return $order->dueOnDelivery();
    }

    /** @return array<int|string, string>|null */
    private static function areas(Order $order, ?string $courier): ?array
    {
        if (! $courier) {
            return null;
        }

        try {
            return app(CourierManager::class)->driver($courier)->areaOptions($order);
        } catch (Throwable) {
            return [];
        }
    }

    private static function guessArea(Order $order): ?int
    {
        $driver = self::defaultCourier() ? app(CourierManager::class)->driver(self::defaultCourier()) : null;

        try {
            return $driver instanceof RedxCourier ? $driver->guessArea($order) : null;
        } catch (Throwable) {
            return null;
        }
    }
}

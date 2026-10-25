<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\StockReason;
use App\Domain\Inventory\StockLedger;
use App\Filament\Resources\StockMovements\StockMovementResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements';

    protected static ?string $title = 'স্টকের হিসাব';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        return StockMovementResource::columns($table, withProduct: false)
            ->modifyQueryUsing(fn ($query) => $query->with(['variant.attributeValues.attribute', 'order', 'user']))
            ->headerActions([
                Action::make('adjust')->label('স্টক সমন্বয়')->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->authorize(fn () => auth()->user()->can('update', $product))
                    ->schema([
                        Select::make('variant_id')->label('ভ্যারিয়েন্ট')->required()
                            ->options(fn () => $product->variants()->with('attributeValues.attribute')->get()->mapWithKeys(fn (ProductVariant $v) => [$v->id => ($v->label() ?: $v->sku).' (স্টক '.$v->stock.')']))
                            ->visible(fn () => $product->isVariable()),
                        TextInput::make('change')->label('কত পিস (কমাতে মাইনাস দিন)')->numeric()->integer()->required()->notIn([0]),
                        TextInput::make('note')->label('কারণ')->placeholder('যেমন: গুনে মেলানো, ড্যামেজ, গিফট')->required()->maxLength(150),
                    ])
                    ->action(function (array $data) use ($product) {
                        DB::transaction(fn () => StockLedger::within(StockReason::Adjustment, function () use ($data, $product) {
                            $row = $product->isVariable() ? ProductVariant::lockForUpdate()->findOrFail($data['variant_id']) : Product::lockForUpdate()->findOrFail($product->id);
                            $row->update(['stock' => max(0, $row->stock + (int) $data['change'])]);
                        }, note: $data['note']));
                        Notification::make()->success()->title('স্টক আপডেট হয়েছে')->send();
                    }),
            ]);
    }
}

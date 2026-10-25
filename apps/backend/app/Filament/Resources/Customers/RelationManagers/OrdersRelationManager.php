<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Domain\Order\Models\Order;
use App\Filament\Resources\Orders\OrderResource;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'অর্ডার';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')->label('অর্ডার'),
                TextColumn::make('created_at')->label('তারিখ')->dateTime('d M Y, h:i A'),
                TextColumn::make('status')->label('স্ট্যাটাস')->badge(),
                TextColumn::make('total')->label('মোট')->formatStateUsing(fn ($state) => Money::format($state)),
            ]);
    }
}

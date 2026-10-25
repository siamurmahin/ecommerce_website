<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Domain\Customer\Models\Customer;
use App\Domain\Reward\Models\PointTransaction;
use App\Domain\Reward\PointLedger;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PointsRelationManager extends RelationManager
{
    protected static string $relationship = 'pointTransactions';

    protected static ?string $title = 'পয়েন্ট';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->points_balance;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn () => 'পয়েন্ট — ব্যালান্স '.$this->getOwnerRecord()->points_balance)
            ->columns([
                TextColumn::make('type')->label('ধরন')->formatStateUsing(fn (string $state) => PointTransaction::LABELS[$state] ?? $state)->badge(),
                TextColumn::make('points')->label('পয়েন্ট')->numeric()
                    ->formatStateUsing(fn (int $state) => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('note')->label('বিবরণ')->placeholder('—')->wrap(),
                TextColumn::make('user.name')->label('যিনি দিয়েছেন')->placeholder('—'),
                TextColumn::make('created_at')->label('তারিখ')->since(),
            ])
            ->headerActions([
                Action::make('adjust')->label('পয়েন্ট যোগ/বাদ')->icon(Heroicon::OutlinedPlusCircle)
                    ->authorize(fn () => auth()->user()->can('update', $this->getOwnerRecord()))
                    ->schema([
                        TextInput::make('points')->label('পয়েন্ট (বাদ দিতে মাইনাস দিন)')->numeric()->integer()->required()->notIn([0]),
                        TextInput::make('note')->label('কারণ')->required()->maxLength(200),
                    ])
                    ->action(function (array $data) {
                        /** @var Customer $customer */
                        $customer = $this->getOwnerRecord();
                        app(PointLedger::class)->post($customer, (int) $data['points'], PointTransaction::ADJUST, note: $data['note'], userId: auth()->id());
                        Notification::make()->success()->title('পয়েন্ট আপডেট হয়েছে')->send();
                    }),
            ]);
    }
}

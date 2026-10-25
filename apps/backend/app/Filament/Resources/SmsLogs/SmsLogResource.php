<?php

namespace App\Filament\Resources\SmsLogs;

use App\Domain\Sms\Jobs\SendSms;
use App\Domain\Sms\Models\SmsLog;
use App\Filament\Resources\SmsLogs\Pages\ListSmsLogs;
use App\Settings\SmsSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Read-only history of every SMS, with a resend for failures. */
class SmsLogResource extends Resource
{
    protected static ?string $model = SmsLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'SMS লগ';

    protected static ?string $modelLabel = 'SMS';

    protected static ?string $pluralModelLabel = 'SMS লগ';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('সময়')->since()->sortable(),
                TextColumn::make('to')->label('নম্বর')->searchable(),
                TextColumn::make('event')->label('ধরন')->formatStateUsing(fn (?string $state) => SmsSettings::EVENTS[$state] ?? $state ?? '—'),
                TextColumn::make('message')->label('মেসেজ')->limit(60)->tooltip(fn (SmsLog $record) => $record->message)->wrap(),
                TextColumn::make('order.number')->label('অর্ডার'),
                TextColumn::make('status')->label('স্ট্যাটাস')->badge()
                    ->formatStateUsing(fn (string $state) => ['queued' => 'লাইনে', 'sent' => 'পাঠানো', 'failed' => 'ব্যর্থ'][$state] ?? $state)
                    ->color(fn (string $state) => ['sent' => 'success', 'failed' => 'danger'][$state] ?? 'gray')
                    ->tooltip(fn (SmsLog $record) => $record->error),
            ])
            ->filters([
                SelectFilter::make('status')->label('স্ট্যাটাস')->options(['queued' => 'লাইনে', 'sent' => 'পাঠানো', 'failed' => 'ব্যর্থ']),
            ])
            ->recordActions([
                Action::make('resend')->label('আবার পাঠান')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (SmsLog $record) => $record->status === 'failed')
                    ->authorize('update')
                    ->action(function (SmsLog $record) {
                        $record->update(['status' => 'queued', 'error' => null]);
                        SendSms::dispatch($record->id);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSmsLogs::route('/')];
    }
}

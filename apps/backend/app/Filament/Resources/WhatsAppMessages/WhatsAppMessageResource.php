<?php

namespace App\Filament\Resources\WhatsAppMessages;

use App\Domain\WhatsApp\Jobs\SendWhatsApp;
use App\Domain\WhatsApp\Models\WhatsAppMessage;
use App\Filament\Resources\WhatsAppMessages\Pages\ListWhatsAppMessages;
use App\Settings\WhatsAppSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Read-only history of every WhatsApp message, with a resend for failures. */
class WhatsAppMessageResource extends Resource
{
    protected static ?string $model = WhatsAppMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'WhatsApp লগ';

    protected static ?string $modelLabel = 'WhatsApp মেসেজ';

    protected static ?string $pluralModelLabel = 'WhatsApp লগ';

    protected static ?string $slug = 'whatsapp-messages';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $statuses = ['queued' => 'লাইনে', 'sent' => 'পাঠানো', 'failed' => 'ব্যর্থ'];

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('সময়')->since()->sortable(),
                TextColumn::make('to')->label('নম্বর')->searchable(),
                TextColumn::make('event')->label('ধরন')->formatStateUsing(fn (?string $state) => WhatsAppSettings::EVENTS[$state] ?? $state ?? '—'),
                TextColumn::make('preview')->label('মেসেজ')->limit(60)->tooltip(fn (WhatsAppMessage $record) => $record->preview)->wrap()
                    ->description(fn (WhatsAppMessage $record) => "{$record->template} · {$record->language}"),
                TextColumn::make('order.number')->label('অর্ডার'),
                TextColumn::make('status')->label('স্ট্যাটাস')->badge()
                    ->formatStateUsing(fn (string $state) => $statuses[$state] ?? $state)
                    ->color(fn (string $state) => ['sent' => 'success', 'failed' => 'danger'][$state] ?? 'gray')
                    ->tooltip(fn (WhatsAppMessage $record) => $record->error),
            ])
            ->filters([SelectFilter::make('status')->label('স্ট্যাটাস')->options($statuses)])
            ->recordActions([
                Action::make('resend')->label('আবার পাঠান')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (WhatsAppMessage $record) => $record->status === 'failed')
                    ->authorize('update')
                    ->action(function (WhatsAppMessage $record) {
                        $record->update(['status' => 'queued', 'error' => null]);
                        SendWhatsApp::dispatch($record->id);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWhatsAppMessages::route('/')];
    }
}

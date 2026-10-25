<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Shipping\Models\Thana;
use App\Support\Phone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Contact and address corrections from the confirmation call. Items and prices stay as ordered. */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('কাস্টমার ও ঠিকানা')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('customer_name')->label('নাম')->required()->maxLength(100),
                        TextInput::make('customer_phone')->label('মোবাইল')->required()->tel()
                            ->dehydrateStateUsing(fn (?string $state) => Phone::normalize($state))
                            ->regex(Phone::PATTERN),
                        TextInput::make('customer_email')->label('ইমেইল')->email(),
                        Select::make('district_id')->label('জেলা')->relationship('district', 'name')->searchable()->preload()->live()
                            ->afterStateUpdated(fn ($set) => $set('thana_id', null)),
                        Select::make('thana_id')->label('থানা/উপজেলা')->searchable()
                            ->options(fn ($get) => Thana::active()->where('district_id', $get('district_id'))->orderBy('name')->pluck('name', 'id')),
                        Textarea::make('address')->label('ঠিকানা')->required()->rows(2)->columnSpanFull(),
                        Textarea::make('customer_note')->label('কাস্টমারের নোট')->rows(2)->columnSpanFull(),
                    ]),
                Section::make('অভ্যন্তরীণ')
                    ->schema([
                        Select::make('payment_status')->label('পেমেন্ট')->options(PaymentStatus::class)->required()->native(false),
                        Textarea::make('internal_note')->label('ইন্টারনাল নোট')->helperText('শুধু স্টাফ দেখতে পাবে')->rows(4),
                    ]),
            ]);
    }
}

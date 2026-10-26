<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Domain\Order\Models\Order;
use App\Support\Money;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn ($state) => Money::format((int) $state);

        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    Section::make('পণ্য')
                        ->schema([
                            RepeatableEntry::make('items')
                                ->hiddenLabel()
                                ->table([
                                    TableColumn::make(''),
                                    TableColumn::make('পণ্য'),
                                    TableColumn::make('দাম'),
                                    TableColumn::make('পরিমাণ'),
                                    TableColumn::make('মোট'),
                                ])
                                ->schema([
                                    ImageEntry::make('image')->disk('public')->imageSize(44),
                                    TextEntry::make('name')->helperText(fn ($record) => collect([$record->variant_label, $record->sku])->filter()->implode(' · ')),
                                    TextEntry::make('unit_price')->formatStateUsing($money),
                                    TextEntry::make('quantity'),
                                    TextEntry::make('line_total')->formatStateUsing($money)->weight('bold'),
                                ]),
                            Group::make([
                                TextEntry::make('subtotal')->label('সাবটোটাল')->formatStateUsing($money)->inlineLabel(),
                                TextEntry::make('delivery_charge')->label('ডেলিভারি চার্জ')->formatStateUsing($money)->inlineLabel(),
                                TextEntry::make('discount')->label('ডিসকাউন্ট')->formatStateUsing($money)->inlineLabel()->visible(fn (Order $record) => $record->discount > 0)
                                    ->helperText(fn (Order $record) => collect($record->discounts ?? [])
                                        ->map(fn ($d) => (match ($d['type']) {
                                            'coupon' => 'কুপন ', 'package' => 'প্যাকেজ: ', 'referral' => 'রেফারেল ', 'points' => '', default => 'বান্ডেল: '
                                        }).$d['label'].' −'.Money::format($d['amount']))->implode(' · ') ?: null),
                                TextEntry::make('total')->label('মোট')->formatStateUsing($money)->inlineLabel()->weight('bold')->size('lg'),
                            ])->extraAttributes(['class' => 'ms-auto max-w-sm']),
                        ]),
                    Section::make('টাইমলাইন')
                        ->collapsible()
                        ->schema([
                            RepeatableEntry::make('statusHistories')
                                ->hiddenLabel()
                                ->contained(false)
                                ->schema([
                                    TextEntry::make('to')->hiddenLabel()->badge()
                                        ->helperText(fn ($record) => collect([
                                            $record->created_at->format('d M Y, h:i A'),
                                            $record->user?->name ?? ($record->from ? null : 'ওয়েবসাইট'),
                                            $record->note,
                                        ])->filter()->implode(' · ')),
                                ]),
                        ]),
                ])->columnSpan(2),
                Group::make([
                    Section::make('স্ট্যাটাস')
                        ->schema([
                            TextEntry::make('status')->label('অর্ডার')->badge()->inlineLabel(),
                            TextEntry::make('payment_status')->label('পেমেন্ট')->badge()->inlineLabel(),
                            TextEntry::make('payment_method')->label('মাধ্যম')->inlineLabel(),
                            TextEntry::make('payment_trx_id')->label('TrxID')->copyable()->inlineLabel()
                                ->visible(fn (Order $record) => filled($record->payment_trx_id))
                                ->helperText(fn (Order $record) => collect([$record->payment_wallet?->getLabel(), $record->payment_sender])->filter()->implode(' · ')),
                            TextEntry::make('advance_amount')->label('অগ্রিম পাঠিয়েছেন')->formatStateUsing($money)->inlineLabel()
                                ->visible(fn (Order $record) => $record->advance_amount > 0),
                            TextEntry::make('paid_amount')->label('যাচাই করা পেমেন্ট')->formatStateUsing($money)->inlineLabel()
                                ->visible(fn (Order $record) => $record->paid_amount > 0),
                            TextEntry::make('due')->label('ডেলিভারিতে নেবে')->state(fn (Order $record) => Money::format($record->dueOnDelivery()))->inlineLabel(),
                            TextEntry::make('payment_note')->label('পেমেন্ট নোট')->inlineLabel()->visible(fn (Order $record) => filled($record->payment_note))
                                ->helperText(fn (Order $record) => $record->payment_verified_at?->format('d M Y, h:i A')),
                            TextEntry::make('created_at')->label('সময়')->dateTime('d M Y, h:i A')->inlineLabel(),
                        ]),
                    Section::make('কুরিয়ার')
                        ->visible(fn (Order $record) => $record->latestShipment !== null)
                        ->schema([
                            TextEntry::make('latestShipment.status')->label(fn (Order $record) => $record->latestShipment?->courierLabel())->badge()->inlineLabel()
                                ->helperText(fn (Order $record) => $record->latestShipment?->raw_status),
                            TextEntry::make('latestShipment.consignment_id')->label('কনসাইনমেন্ট')->copyable()->inlineLabel()
                                ->url(fn (Order $record) => $record->latestShipment?->trackingUrl())->openUrlInNewTab(),
                            TextEntry::make('latestShipment.cod_amount')->label('COD')->formatStateUsing($money)->inlineLabel(),
                            TextEntry::make('latestShipment.delivery_fee')->label('কুরিয়ার চার্জ')->formatStateUsing($money)->inlineLabel()->placeholder('—'),
                            TextEntry::make('latestShipment.synced_at')->label('শেষ আপডেট')->since()->inlineLabel(),
                        ]),
                    Section::make('ফ্রড চেক')
                        ->schema([
                            TextEntry::make('fraud')->hiddenLabel()->badge()
                                ->state(fn (Order $record) => match ($record->fraudCheck?->risk()) {
                                    'risky' => 'ঝুঁকিপূর্ণ',
                                    'medium' => 'সতর্ক থাকুন',
                                    'good' => 'ভালো কাস্টমার',
                                    'new' => 'আগের হিস্ট্রি নেই',
                                    default => 'এখনো চেক হয়নি',
                                })
                                ->color(fn (Order $record) => $record->fraudCheck?->riskColor() ?? 'gray')
                                ->helperText(fn (Order $record) => $record->fraudCheck
                                    ? $record->fraudCheck->summary().' · '.$record->fraudCheck->checked_at->diffForHumans()
                                    : 'উপরের "ফ্রড চেক" বাটনে চাপুন'),
                            TextEntry::make('fraud_couriers')->hiddenLabel()
                                ->state(fn (Order $record) => collect($record->fraudCheck?->couriers ?? [])
                                    ->map(fn ($c, $name) => ucfirst($name).": {$c['delivered']}/{$c['total']}")->implode(' · ') ?: null)
                                ->visible(fn (Order $record) => filled($record->fraudCheck?->couriers)),
                        ]),
                    Section::make('কাস্টমার')
                        ->schema([
                            TextEntry::make('customer_name')->label('নাম'),
                            TextEntry::make('customer_phone')->label('মোবাইল')->copyable()
                                ->url(fn (Order $record) => 'tel:'.$record->customer_phone),
                            TextEntry::make('customer_email')->label('ইমেইল')->placeholder('—'),
                            TextEntry::make('address')->label('ঠিকানা')
                                ->helperText(fn (Order $record) => collect([$record->thana_name, $record->district_name, $record->delivery_zone_name])->filter()->implode(' · ')),
                            TextEntry::make('customer_note')->label('কাস্টমারের নোট')->placeholder('—'),
                            TextEntry::make('internal_note')->label('ইন্টারনাল নোট')->placeholder('—'),
                        ]),
                    Section::make('সোর্স ও ডিভাইস')
                        ->collapsed()
                        ->schema([
                            TextEntry::make('source')->label('সোর্স')->inlineLabel(),
                            TextEntry::make('landingPage.title')->label('ল্যান্ডিং পেজ')->inlineLabel()->visible(fn ($record) => $record->landing_page_id !== null),
                            TextEntry::make('utm')->label('UTM')->placeholder('—')
                                ->formatStateUsing(fn ($state) => is_array($state) ? collect($state)->map(fn ($v, $k) => "{$k}={$v}")->implode(', ') : $state),
                            TextEntry::make('ip')->label('IP')->placeholder('—')->inlineLabel(),
                            TextEntry::make('user_agent')->label('ডিভাইস')->placeholder('—'),
                        ]),
                ]),
            ]);
    }
}

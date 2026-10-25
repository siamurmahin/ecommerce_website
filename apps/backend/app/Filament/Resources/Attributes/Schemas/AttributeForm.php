<?php

namespace App\Filament\Resources\Attributes\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('অ্যাট্রিবিউট')
                    ->description('ভ্যারিয়েন্টের ধরন, যেমন সাইজ, রং, ফ্যাব্রিক বা পরিমাণ (ml)।')
                    ->columns(2)
                    ->schema([
                        Fields::name(),
                        Fields::nameEn(),
                        Fields::slug(),
                        ToggleButtons::make('type')
                            ->label('দেখানোর ধরন')
                            ->options(['button' => 'বাটন', 'color' => 'রঙের সোয়াচ'])
                            ->default('button')
                            ->inline()
                            ->live(),
                    ]),
                Section::make('ভ্যালু')
                    ->schema([
                        Repeater::make('values')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->columns(3)
                            ->addActionLabel('ভ্যালু যোগ করুন')
                            ->itemLabel(fn (array $state) => $state['value'] ?? null)
                            ->schema([
                                TextInput::make('value')->label('ভ্যালু (বাংলা)')->required(),
                                TextInput::make('value_en')->label('ভ্যালু (English)'),
                                ColorPicker::make('color_hex')
                                    ->label('রং')
                                    ->visible(fn (Get $get) => $get('../../type') === 'color'),
                            ]),
                    ]),
            ]);
    }
}

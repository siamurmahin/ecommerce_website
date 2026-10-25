<?php

namespace App\Filament\Support;

use App\Support\Money;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

/** Field presets shared across admin resources. */
final class Fields
{
    /** Taka input backed by an integer poisha column. */
    public static function money(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->step(0.01)
            ->prefix('৳')
            ->formatStateUsing(fn ($state) => Money::toTaka($state === null ? null : (int) $state))
            ->dehydrateStateUsing(fn ($state) => Money::toPoisha($state));
    }

    /** Bangla name; fills the slug on create unless an English name already did. */
    public static function name(string $label = 'নাম (বাংলা)'): TextInput
    {
        return TextInput::make('name')
            ->label($label)
            ->required()
            ->maxLength(255)
            ->live(onBlur: true)
            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation) {
                if ($operation === 'create' && blank($get('name_en'))) {
                    $set('slug', Str::slug((string) $state));
                }
            });
    }

    /** English name; preferred source for the slug since it gives clean URLs. */
    public static function nameEn(string $label = 'নাম (English)'): TextInput
    {
        return TextInput::make('name_en')
            ->label($label)
            ->maxLength(255)
            ->live(onBlur: true)
            ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                if ($operation === 'create' && filled($state)) {
                    $set('slug', Str::slug($state));
                }
            });
    }

    public static function slug(): TextInput
    {
        return TextInput::make('slug')
            ->label('URL (slug)')
            ->helperText('ইংরেজি অক্ষরে, যেমন cotton-kabli-panjabi')
            ->required()
            ->alphaDash()
            ->maxLength(255)
            ->unique(ignoreRecord: true);
    }
}

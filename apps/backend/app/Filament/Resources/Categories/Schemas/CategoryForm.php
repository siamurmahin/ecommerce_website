<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    Section::make('মূল তথ্য')
                        ->columns(2)
                        ->schema([
                            Fields::name(),
                            Fields::nameEn(),
                            Fields::slug(),
                            Select::make('parent_id')
                                ->label('প্যারেন্ট ক্যাটাগরি')
                                ->helperText('খালি রাখলে এটা মূল ক্যাটাগরি')
                                ->relationship('parent', 'name', fn ($query, $record) => $query->when($record, fn ($q) => $q->whereKeyNot($record->id)))
                                ->searchable()
                                ->preload(),
                            Textarea::make('description')->label('বিবরণ (বাংলা)')->rows(3),
                            Textarea::make('description_en')->label('বিবরণ (English)')->rows(3),
                        ]),
                    Section::make('SEO')
                        ->collapsed()
                        ->schema([
                            TextInput::make('meta_title')->label('মেটা টাইটেল')->maxLength(70),
                            Textarea::make('meta_description')->label('মেটা বিবরণ')->maxLength(160)->rows(2),
                        ]),
                ])->columnSpan(2),
                Group::make([
                    Section::make('স্ট্যাটাস')
                        ->schema([
                            Toggle::make('is_active')->label('সাইটে দেখাবে')->default(true),
                            Toggle::make('is_featured')->label('হোমপেজে দেখাবে'),
                            TextInput::make('sort_order')->label('ক্রম')->numeric()->default(0),
                        ]),
                    Section::make('ছবি')
                        ->schema([
                            FileUpload::make('image')
                                ->hiddenLabel()
                                ->image()
                                ->imageEditor()
                                ->disk('public')
                                ->directory('categories')
                                ->maxSize(2048),
                        ]),
                ]),
            ]);
    }
}

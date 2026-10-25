<?php

namespace App\Filament\Resources\Banners;

use App\Domain\Content\Enums\BannerPlacement;
use App\Domain\Content\Models\Banner;
use App\Filament\Resources\Banners\Pages\ManageBanners;
use App\Support\StorefrontCache;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|UnitEnum|null $navigationGroup = 'কনটেন্ট';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'ব্যানার';

    protected static ?string $pluralModelLabel = 'ব্যানার ও স্লাইডার';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ToggleButtons::make('placement')->label('কোথায় দেখাবে')->options(BannerPlacement::class)->default(BannerPlacement::Hero)->inline()->required(),
                Grid::make(2)->schema([
                    TextInput::make('title')->label('শিরোনাম (বাংলা)')->helperText('ছবির alt টেক্সট হিসেবেও ব্যবহার হয়')->required()->maxLength(150),
                    TextInput::make('title_en')->label('শিরোনাম (English)')->maxLength(150),
                    TextInput::make('subtitle')->label('সাবটাইটেল (বাংলা)')->maxLength(200),
                    TextInput::make('subtitle_en')->label('সাবটাইটেল (English)')->maxLength(200),
                ]),
                Grid::make(2)->schema([
                    FileUpload::make('image')->label('ছবি (ডেস্কটপ)')->helperText('হিরো: ২০০০×৮০০, প্রোমো: ১০০০×৪০০')
                        ->image()->disk('public')->directory('banners')->maxSize(3072)->required(),
                    FileUpload::make('image_mobile')->label('ছবি (মোবাইল, ঐচ্ছিক)')->helperText('৮০০×৮০০; না দিলে ডেস্কটপের ছবি')
                        ->image()->disk('public')->directory('banners')->maxSize(2048),
                ]),
                TextInput::make('link')->label('লিংক')->helperText('যেমন /c/men বা /p/oud-attar')->maxLength(255),
                Grid::make(3)->schema([
                    DateTimePicker::make('starts_at')->label('শুরু (ঐচ্ছিক)'),
                    DateTimePicker::make('ends_at')->label('শেষ (ঐচ্ছিক)')->after('starts_at'),
                    Toggle::make('is_active')->label('সক্রিয়')->default(true)->inline(false),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            // Drag-reordering is one mass update with no model events.
            ->afterReordering(fn () => StorefrontCache::revalidate('home'))
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->imageHeight(48)->extraImgAttributes(['class' => 'rounded-lg']),
                TextColumn::make('title')->label('শিরোনাম')->description(fn (Banner $record) => $record->link)->searchable(['title', 'title_en']),
                TextColumn::make('placement')->label('জায়গা')->badge(),
                TextColumn::make('ends_at')->label('শেষ')->dateTime('d M Y')->placeholder('—'),
                ToggleColumn::make('is_active')->label('সক্রিয়'),
            ])
            ->filters([
                SelectFilter::make('placement')->label('জায়গা')->options(BannerPlacement::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBanners::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\StaticPages;

use App\Domain\Content\Models\Page;
use App\Filament\Resources\StaticPages\Pages\CreateStaticPage;
use App\Filament\Resources\StaticPages\Pages\EditStaticPage;
use App\Filament\Resources\StaticPages\Pages\ListStaticPages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class StaticPageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|UnitEnum|null $navigationGroup = 'কনটেন্ট';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'পেজ';

    protected static ?string $pluralModelLabel = 'স্ট্যাটিক পেজ';

    protected static ?string $slug = 'pages';

    /** Storefront routes a page slug must not shadow. */
    public const RESERVED_SLUGS = ['en', 'bn', 'shop', 'search', 'cart', 'checkout', 'categories', 'wishlist', 'order', 'track-order', 'account', 'c', 'p', 'l', 'api'];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()->schema([
                    Grid::make(2)->schema([
                        TextInput::make('title')->label('শিরোনাম (বাংলা)')->required()->maxLength(150)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set, $get, string $operation) => $operation === 'create' && blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
                        TextInput::make('title_en')->label('শিরোনাম (English)')->maxLength(150),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('slug')->label('লিংক (slug)')->prefix('/')->helperText('যেমন about, return-policy — সাইটে /about এ খুলবে')
                            ->required()->maxLength(100)->alphaDash()->unique(ignoreRecord: true)
                            ->notIn(self::RESERVED_SLUGS)->validationMessages(['not_in' => 'এই লিংকটা সাইটের অন্য পেজে ব্যবহার হচ্ছে']),
                        Toggle::make('is_active')->label('প্রকাশিত')->default(true)->inline(false),
                    ]),
                ]),
                Section::make('কনটেন্ট')->schema([
                    Tabs::make()->contained(false)->tabs([
                        Tab::make('বাংলা')->schema([RichEditor::make('content')->label('')]),
                        Tab::make('English')->schema([RichEditor::make('content_en')->label('')->helperText('খালি রাখলে বাংলাটাই দেখাবে')]),
                    ]),
                ]),
                Section::make('SEO')->collapsed()->schema([
                    TextInput::make('meta_title')->label('মেটা টাইটেল')->helperText('খালি রাখলে শিরোনাম')->maxLength(70),
                    Textarea::make('meta_description')->label('মেটা ডেসক্রিপশন')->helperText('খালি রাখলে কনটেন্টের শুরু থেকে')->rows(2)->maxLength(300),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')->label('শিরোনাম')->description(fn (Page $record) => '/'.$record->slug)->searchable(['title', 'title_en', 'slug']),
                TextColumn::make('updated_at')->label('আপডেট')->since(),
                ToggleColumn::make('is_active')->label('প্রকাশিত'),
            ])
            ->recordActions([
                Action::make('view')->label('দেখুন')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Page $record) => rtrim((string) config('shop.storefront_url'), '/').'/'.$record->slug, shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaticPages::route('/'),
            'create' => CreateStaticPage::route('/create'),
            'edit' => EditStaticPage::route('/{record}/edit'),
        ];
    }
}

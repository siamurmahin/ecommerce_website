<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Domain\Catalog\Enums\ProductBadge;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Product;
use App\Filament\Resources\Products\Actions\WriteWithAi;
use App\Filament\Support\Fields;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    self::basics(),
                    self::content(),
                    self::media(),
                    self::pricing(),
                    self::variants(),
                    self::specifications(),
                    self::relatedOffers(),
                    self::seo(),
                ])->columnSpan(2),
                Group::make([
                    self::status(),
                    self::organization(),
                    self::shipping(),
                ]),
            ]);
    }

    private static function isVariable(Get $get): bool
    {
        $type = $get('type');

        return ($type instanceof ProductType ? $type : ProductType::tryFrom((string) $type)) === ProductType::Variable;
    }

    private static function basics(): Section
    {
        return Section::make('মূল তথ্য')
            ->columns(2)
            ->schema([
                Fields::name('পণ্যের নাম (বাংলা)'),
                Fields::nameEn('পণ্যের নাম (English)'),
                Fields::slug(),
                TextInput::make('sku')
                    ->label('SKU (প্রোডাক্ট কোড)')
                    ->unique(ignoreRecord: true)
                    ->maxLength(64),
            ]);
    }

    private static function content(): Section
    {
        return Section::make('বিবরণ')
            ->key('content')
            ->afterHeader([WriteWithAi::make()])
            ->schema([
                Tabs::make()
                    ->contained(false)
                    ->tabs([
                        Tab::make('বাংলা')->schema([
                            Textarea::make('short_description')
                                ->label('সংক্ষিপ্ত হাইলাইট')
                                ->helperText('প্রতি লাইনে একটা পয়েন্ট, প্রোডাক্ট পেজে বুলেট হিসেবে দেখাবে')
                                ->rows(4),
                            RichEditor::make('description')->label('বিস্তারিত বিবরণ'),
                        ]),
                        Tab::make('English')->schema([
                            Textarea::make('short_description_en')->label('Short highlights')->rows(4),
                            RichEditor::make('description_en')->label('Full description'),
                        ]),
                    ]),
            ]);
    }

    private static function media(): Section
    {
        return Section::make('ছবি')
            ->description('প্রথম ছবিটা কভার হিসেবে দেখাবে। টেনে ক্রম বদলানো যায়।')
            ->schema([
                Repeater::make('images')
                    ->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->grid(3)
                    ->addActionLabel('ছবি যোগ করুন')
                    ->defaultItems(0)
                    ->schema([
                        FileUpload::make('path')
                            ->hiddenLabel()
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('products')
                            ->maxSize(4096)
                            ->required(),
                        TextInput::make('alt')->hiddenLabel()->placeholder('ছবির বর্ণনা (SEO)'),
                    ]),
            ]);
    }

    private static function pricing(): Section
    {
        return Section::make('দাম ও স্টক')
            ->columns(3)
            ->schema([
                ToggleButtons::make('type')
                    ->label('পণ্যের ধরন')
                    ->options(ProductType::class)
                    ->default(ProductType::Simple)
                    ->inline()
                    ->live()
                    ->columnSpanFull(),
                Fields::money('price', 'বিক্রয় মূল্য')
                    ->required()
                    ->helperText(fn (Get $get) => self::isVariable($get) ? 'ভ্যারিয়েন্টের দাম না দিলে এটা ব্যবহার হবে' : null),
                Fields::money('compare_price', 'আগের দাম (কাটা দাম)')
                    ->helperText('ছাড় দেখাতে চাইলে'),
                Fields::money('cost_price', 'ক্রয় মূল্য')
                    ->helperText('শুধু লাভের রিপোর্টে, কাস্টমার দেখবে না'),
                TextInput::make('stock')
                    ->label('স্টক')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->hidden(fn (Get $get) => self::isVariable($get)),
                TextInput::make('low_stock_threshold')
                    ->label('লো-স্টক সতর্কতা')
                    ->numeric()
                    ->minValue(0)
                    ->default(5),
            ]);
    }

    private static function variants(): Section
    {
        return Section::make('ভ্যারিয়েন্ট')
            ->description('প্রতিটা সাইজ/রঙের জন্য আলাদা দাম, স্টক আর ছবি।')
            ->visible(fn (Get $get) => self::isVariable($get))
            ->schema([
                Repeater::make('variants')
                    ->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->columns(4)
                    ->collapsible()
                    ->addActionLabel('ভ্যারিয়েন্ট যোগ করুন')
                    ->itemLabel(fn (array $state) => filled($state['sku'] ?? null) ? $state['sku'] : null)
                    ->schema([
                        Select::make('attributeValues')
                            ->label('অপশন')
                            ->relationship('attributeValues', 'value')
                            ->getOptionLabelFromRecordUsing(fn (AttributeValue $record) => "{$record->attribute->name}: {$record->value}")
                            ->multiple()
                            ->preload()
                            ->required()
                            ->columnSpan(2),
                        TextInput::make('sku')->label('SKU')->distinct()->maxLength(64),
                        TextInput::make('stock')->label('স্টক')->numeric()->minValue(0)->default(0)->required(),
                        Fields::money('price', 'দাম')->required(),
                        Fields::money('compare_price', 'আগের দাম'),
                        FileUpload::make('image')
                            ->label('ছবি')
                            ->image()
                            ->disk('public')
                            ->directory('products/variants')
                            ->maxSize(4096),
                        Toggle::make('is_active')->label('সক্রিয়')->default(true)->inline(false),
                    ]),
            ]);
    }

    private static function specifications(): Section
    {
        return Section::make('স্পেসিফিকেশন')
            ->description('যেমন ফ্যাব্রিক: সুতি, ওজন: ২৫০ গ্রাম')
            ->collapsible()
            ->schema([
                Repeater::make('specifications')
                    ->hiddenLabel()
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('সারি যোগ করুন')
                    ->reorderable()
                    ->schema([
                        TextInput::make('label')->label('বিষয়')->required(),
                        TextInput::make('value')->label('তথ্য')->required(),
                    ]),
            ]);
    }

    private static function relatedOffers(): Section
    {
        $picker = fn (string $name, string $label, string $help) => Select::make($name)->label($label)->helperText($help)
            ->relationship($name, 'name', fn (Builder $query, ?Product $record) => $query->when($record, fn ($q) => $q->whereKeyNot($record->id)))
            ->multiple()->searchable()->preload()->maxItems(8);

        return Section::make('আপ-সেল ও ক্রস-সেল')
            ->collapsible()
            ->columns(2)
            ->schema([
                $picker('upsells', 'আপ-সেল', 'এই পণ্যের পেজে "আরও ভালো বিকল্প" হিসেবে দেখাবে — সাধারণত দামি/প্রিমিয়াম ভার্সন।'),
                $picker('crossSells', 'ক্রস-সেল', 'এই পণ্য কার্টে থাকলে "এটাও নিতে পারেন" হিসেবে দেখাবে — যেমন পাঞ্জাবির সাথে আতর।'),
            ]);
    }

    private static function seo(): Section
    {
        return Section::make('SEO')
            ->collapsed()
            ->schema([
                TextInput::make('meta_title')->label('মেটা টাইটেল')->maxLength(70),
                Textarea::make('meta_description')->label('মেটা বিবরণ')->maxLength(160)->rows(2),
            ]);
    }

    private static function status(): Section
    {
        return Section::make('প্রকাশ')
            ->schema([
                Toggle::make('is_active')->label('সাইটে দেখাবে')->default(true),
                DateTimePicker::make('published_at')
                    ->label('প্রকাশের সময়')
                    ->helperText('ভবিষ্যতের সময় দিলে তখন থেকে দেখাবে')
                    ->native(false),
            ]);
    }

    private static function organization(): Section
    {
        return Section::make('ক্যাটাগরি ও লেবেল')
            ->schema([
                Select::make('category_id')
                    ->label('ক্যাটাগরি')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('brand_id')
                    ->label('ব্র্যান্ড')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('badge')
                    ->label('ব্যাজ')
                    ->options(ProductBadge::class)
                    ->placeholder('কোনো ব্যাজ নেই'),
                Toggle::make('is_featured')->label('হোমপেজে বাছাই করা'),
            ]);
    }

    private static function shipping(): Section
    {
        return Section::make('ডেলিভারি')
            ->schema([
                Toggle::make('free_delivery')->label('ফ্রি ডেলিভারি'),
                TextInput::make('weight_grams')->label('ওজন')->numeric()->suffix('গ্রাম'),
            ]);
    }
}

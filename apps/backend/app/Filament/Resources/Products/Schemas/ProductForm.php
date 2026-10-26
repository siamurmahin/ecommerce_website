<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Domain\Catalog\Enums\ProductBadge;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Support\QuoteLine;
use App\Filament\Resources\Products\Actions\WriteWithAi;
use App\Filament\Support\Fields;
use App\Settings\CostSettings;
use App\Support\Money;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
                    self::costs(),
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
                Toggle::make('preorder')->label('প্রি-অর্ডার নেওয়া যাবে')->live()->columnSpanFull()
                    ->helperText('স্টক শেষ হলেও কাস্টমার অর্ডার করতে পারবেন (এক অর্ডারে সর্বোচ্চ '.QuoteLine::MAX_PREORDER.' পিস); সেই পিসগুলো স্টক থেকে কাটা হয় না, অর্ডারে "প্রি-অর্ডার" লেখা থাকে।'),
                TextInput::make('preorder_note')->label('প্রি-অর্ডার নোট (বাংলা)')->maxLength(150)->placeholder('যেমন: নভেম্বরের দ্বিতীয় সপ্তাহে ডেলিভারি')
                    ->visible(fn (Get $get) => (bool) $get('preorder'))->columnSpan(2),
                TextInput::make('preorder_note_en')->label('Pre-order note (English)')->maxLength(150)
                    ->visible(fn (Get $get) => (bool) $get('preorder')),
            ]);
    }

    /** Only for the profit report; customers never see these. */
    private static function costs(): Section
    {
        $settings = app(CostSettings::class);
        $money = fn (?int $poisha) => Money::format($poisha ?? 0);

        return Section::make('খরচের হিসাব')
            ->description('শুধু লাভ-ক্ষতির রিপোর্টে লাগে, কাস্টমার দেখবে না। অর্ডারের সময়ের খরচ অর্ডারে সেভ থাকে, তাই পরে বদলালে পুরোনো হিসাব বদলায় না।')
            ->icon(Heroicon::OutlinedCalculator)
            ->collapsible()
            ->columns(2)
            ->schema([
                Fields::money('cost_price', 'ক্রয় মূল্য (প্রতি পিস)')->live(onBlur: true)
                    ->helperText('ভ্যারিয়েবল পণ্যে সব ভ্যারিয়েন্টের জন্য একই'),
                TextEntry::make('cost_total')->label('প্রতি পিসে মোট খরচ')
                    ->state(fn (Get $get) => $money(self::unitCost($get, $settings)))
                    ->helperText('ক্রয় মূল্য + নিচের খরচ (ডেলিভারি বাদে)'),
                Repeater::make('extra_costs')->label('এই পণ্যের আলাদা খরচ (প্রতি পিস)')
                    ->schema([
                        TextInput::make('label')->label('খরচ')->placeholder('যেমন: র‍্যাপিং, ক্যারিং')->required()->maxLength(60),
                        Fields::money('amount', 'টাকা')->required()->live(onBlur: true),
                    ])
                    ->columns(2)->defaultItems(0)->maxItems(10)->reorderable(false)
                    ->addActionLabel('খরচ যোগ করুন')
                    ->live()
                    ->columnSpanFull(),
                Toggle::make('shared_costs')->label('সব পণ্যের সাধারণ খরচও যোগ হবে')->default(true)->live()
                    ->helperText($settings->shared === []
                        ? 'সেটিংস → খরচ-এ এখনো কোনো সাধারণ খরচ নেই।'
                        : 'সেটিংস → খরচ থেকে: '.collect($settings->shared)->map(fn (array $c) => $c['label'].' '.$money((int) $c['amount']))->implode(', '))
                    ->columnSpanFull(),
                Toggle::make('owner_pays_delivery')->label('ফ্রি ডেলিভারির চার্জ দোকান নিজে দেয়')->default(true)->live()
                    ->helperText('কাস্টমার চার্জ না দিলেও কুরিয়ারকে যা দিতে হয়, সেটা এই পণ্যের খরচে যোগ হবে।')
                    ->visible(fn (Get $get) => (bool) $get('free_delivery')),
                Fields::money('delivery_cost', 'প্রতি অর্ডারে কত দেয়')
                    ->placeholder('খালি রাখলে জোনের ডেলিভারি চার্জ')
                    ->visible(fn (Get $get) => $get('free_delivery') && $get('owner_pays_delivery')),
            ]);
    }

    /** Live total of the per-unit cost fields, in poisha. */
    private static function unitCost(Get $get, CostSettings $settings): int
    {
        $own = collect($get('extra_costs') ?? [])->sum(fn (array $c) => Money::toPoisha($c['amount'] ?? null) ?? 0);

        return (int) (Money::toPoisha($get('cost_price')) ?? 0) + (int) $own + ($get('shared_costs') ? $settings->sharedPerUnit() : 0);
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
                        Fields::money('cost_price', 'ক্রয় মূল্য')->helperText('খালি থাকলে পণ্যের ক্রয় মূল্য ধরা হবে'),
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
                Toggle::make('free_delivery')->label('ফ্রি ডেলিভারি')->live()
                    ->helperText(fn (Get $get) => $get('free_delivery') ? 'চার্জ কে দেবে — "খরচের হিসাব" অংশে ঠিক করুন।' : null),
                TextInput::make('weight_grams')->label('ওজন')->numeric()->suffix('গ্রাম'),
            ]);
    }
}

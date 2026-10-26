<?php

namespace App\Filament\Resources\LandingPages;

use App\Domain\Marketing\Models\LandingPage;
use App\Domain\Marketing\Support\LandingTemplates;
use App\Domain\Order\Enums\OrderStatus;
use App\Filament\Pages\ManageHomepage;
use App\Filament\Resources\LandingPages\Pages\CreateLandingPage;
use App\Filament\Resources\LandingPages\Pages\EditLandingPage;
use App\Filament\Resources\LandingPages\Pages\ListLandingPages;
use App\Filament\Support\Fields;
use App\Filament\Support\LandingPreview;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as Query;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class LandingPageResource extends Resource
{
    protected static ?string $model = LandingPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static string|UnitEnum|null $navigationGroup = 'মার্কেটিং';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'ল্যান্ডিং পেজ';

    protected static ?string $pluralModelLabel = 'ল্যান্ডিং পেজ';

    /** Block names and the one-line hint shown under each preview in the "add block" picker. */
    public const BLOCKS = [
        'announcement' => ['ঘোষণার বার', 'পেজের একদম উপরে সরু রঙিন বার — অফার বা ডেলিভারির খবর।'],
        'hero' => ['হিরো', 'প্রথম স্ক্রিন: বড় শিরোনাম, ছবি, দাম আর অর্ডার বাটন।'],
        'trust' => ['ভরসার স্ট্রিপ', 'ছোট আইকনসহ ভরসার কথা, সাধারণত হিরোর নিচে।'],
        'offer' => ['অফার মূল্য', 'আগের দাম কাটা, বড় অফার দাম, ছাড় আর কাউন্টডাউন।'],
        'notice' => ['বিশেষ দ্রষ্টব্য', 'নম্বর দেওয়া জরুরি কথা — যেমন আগে টাকা লাগবে না।'],
        'checklist' => ['যা পাচ্ছেন (তালিকা)', 'প্যাকেজের প্রতিটা জিনিস ছবি আর ছোট বিবরণসহ।'],
        'benefits' => ['সুবিধা / কেন কিনবেন', 'আইকনসহ সুবিধার কার্ড।'],
        'features' => ['নম্বর দেওয়া ফিচার', 'নম্বর দেওয়া কার্ড, ভেতরে পয়েন্ট আকারে বিস্তারিত।'],
        'gallery' => ['ছবি গ্যালারি', 'পণ্যের আরও ছবি, বড়-ছোট গ্রিডে।'],
        'video' => ['ভিডিও', 'YouTube বা Facebook ভিডিও।'],
        'text' => ['লেখা', 'পণ্যের গল্প বা বিস্তারিত, নিজের মতো ফরম্যাট করে।'],
        'reviews' => ['রিভিউ', 'কাস্টমারের রিভিউ কার্ড, চাইলে স্ক্রিনশটসহ।'],
        'faq' => ['প্রশ্ন ও উত্তর', 'খুলে-বন্ধ করা যায় এমন প্রশ্নোত্তর।'],
        'cta' => ['অর্ডার বাটন', 'বড় রঙিন অংশ, বাটনে চাপলে অর্ডার ফর্মে যায়।'],
        'call' => ['ফোনে অর্ডার', 'হটলাইনে সরাসরি কল করার বড় বাটন।'],
        'order_form' => ['অর্ডার ফর্ম', 'পেজেই অর্ডার: ভ্যারিয়েন্ট, পরিমাণ বা প্যাকেজ, ঠিকানা, পেমেন্ট।'],
    ];

    public static function url(LandingPage $page): string
    {
        return rtrim((string) config('shop.storefront_url'), '/').'/l/'.$page->slug;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()->schema([
                    Grid::make(2)->schema([
                        TextInput::make('title')->label('পেজের নাম')->required()->maxLength(150)
                            ->helperText('ব্রাউজারের ট্যাবে আর অর্ডার রিপোর্টে দেখাবে')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set, $get, string $operation) => $operation === 'create' && blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
                        TextInput::make('slug')->label('লিংক (slug)')->prefix('/l/')->helperText('ইংরেজি অক্ষরে, যেমন eid-attar — অ্যাডে এই লিংক দিন')
                            ->required()->maxLength(100)->alphaDash()->unique(ignoreRecord: true),
                        Select::make('product_id')->label('কোন পণ্য বিক্রি হবে')->relationship('product', 'name')->searchable()->preload()->required()
                            ->helperText('অর্ডার ফর্মে এই পণ্যের দাম, ভ্যারিয়েন্ট আর স্টক আসবে'),
                        Grid::make(2)->schema([
                            Toggle::make('is_active')->label('প্রকাশিত')->default(true)->inline(false),
                            Toggle::make('is_indexable')->label('গুগলে দেখাবে')->default(false)->inline(false)
                                ->helperText('অ্যাডের পেজ সাধারণত বন্ধ রাখা হয়'),
                        ])->columnSpan(1),
                    ]),
                ]),
                Section::make('ডিজাইন')
                    ->description('রেডিমেড ডিজাইন বেছে নিন — পেজের চেহারা এটা ঠিক করে। নতুন পেজে ডিজাইন বাছলে সেই ডিজাইনের শুরুর ব্লকগুলোও বসে যায়; পরে ডিজাইন বদলালে শুধু চেহারা বদলায়, আপনার লেখা-ছবি থাকে।')
                    ->collapsible()
                    ->schema([
                        Radio::make('template')->hiddenLabel()
                            ->options(collect(array_keys(LandingTemplates::TEMPLATES))->mapWithKeys(fn (string $key) => [$key => LandingPreview::design($key)])->all())
                            ->descriptions(LandingTemplates::descriptions())
                            ->default(LandingTemplates::DEFAULT)->required()->columns(['sm' => 2, 'lg' => 3, 'xl' => 4])
                            ->live()
                            ->afterStateUpdated(fn (?string $state, $set, string $operation) => $operation === 'create' && LandingTemplates::exists($state) ? $set('blocks', self::keyed(LandingTemplates::blocks($state))) : null)
                            ->hintAction(Action::make('applyTemplateBlocks')->label('এই ডিজাইনের শুরুর ব্লক বসান')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                                ->visible(fn (string $operation) => $operation === 'edit')
                                ->requiresConfirmation()
                                ->modalDescription('এখনকার সব ব্লক মুছে বেছে নেওয়া ডিজাইনের শুরুর ব্লক বসবে। সেভ না করা পর্যন্ত পেজে কিছু বদলাবে না।')
                                ->action(fn ($get, $set) => $set('blocks', self::keyed(LandingTemplates::blocks((string) $get('template')))))),
                    ]),
                Section::make('A/B টেস্ট')
                    ->description('একই অ্যাড লিংকের ভিজিটরদের মূল পেজ আর ভ্যারিয়েন্টগুলোর মধ্যে ভাগ করে দেয়; প্রত্যেক ভিজিটর সবসময় একই ভার্সন দেখে। তালিকায় ভিউ, অর্ডার আর কনভার্শন রেট মিলিয়ে দেখুন কোনটা ভালো চলছে।')
                    ->collapsed(fn (?LandingPage $record) => ! $record?->isVariant() && ! $record?->variants()->exists())
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('variant_of_id')->label('কোন পেজের ভ্যারিয়েন্ট')
                                ->relationship('parent', 'title', fn (Query $query, ?LandingPage $record) => $query->whereNull('variant_of_id')->when($record, fn ($q) => $q->whereKeyNot($record->id)))
                                ->searchable()->preload()->placeholder('এটা মূল পেজ')
                                ->disabled(fn (?LandingPage $record) => (bool) $record?->variants()->exists())
                                ->helperText('ভ্যারিয়েন্টের নিজের লিংকে অ্যাড দেবেন না — মূল পেজের লিংকেই ভাগ হয়।'),
                            TextInput::make('traffic_weight')->label('ট্রাফিকের ভাগ (ওজন)')->numeric()->minValue(0)->maxValue(100)->default(50)->required()
                                ->helperText('যেমন মূল ৫০ আর ভ্যারিয়েন্ট ৫০ = অর্ধেক অর্ধেক। ০ দিলে এই ভার্সনে কেউ যাবে না।'),
                        ]),
                    ]),
                Section::make('ব্লক')
                    ->description('উপর-নিচে টেনে ক্রম বদলান। অর্ডার ফর্ম ব্লক না দিলে পেজের শেষে নিজে থেকে যোগ হবে। হেডার-ফুটার ছাড়া আলাদা লেআউটে দেখাবে, যাতে কাস্টমার অর্ডারেই মন দেয়।')
                    ->schema([self::blocks()]),
                Section::make('SEO ও শেয়ার')->collapsed()->schema([
                    TextInput::make('meta_title')->label('মেটা টাইটেল')->helperText('খালি রাখলে পেজের নাম')->maxLength(70),
                    Textarea::make('meta_description')->label('মেটা ডেসক্রিপশন')->helperText('খালি রাখলে পণ্যের ছোট বিবরণ')->rows(2)->maxLength(300),
                    FileUpload::make('og_image')->label('শেয়ার ছবি (১২০০×৬৩০)')->helperText('ফেসবুকে লিংক শেয়ার করলে দেখাবে; খালি রাখলে পণ্যের ছবি')
                        ->image()->disk('public')->directory('landing')->maxSize(2048),
                ]),
            ]);
    }

    public static function blocks(): Builder
    {
        $title = fn (string $label = 'শিরোনাম') => TextInput::make('title')->label($label)->maxLength(150);
        $subtitle = fn (string $label = 'ছোট লেখা') => Textarea::make('subtitle')->label($label)->rows(2)->maxLength(400);
        $image = fn (string $name, string $label) => FileUpload::make($name)->label($label)->image()->disk('public')->directory('landing')->maxSize(3072);
        $eyebrow = fn () => TextInput::make('badge')->label('ছোট লেবেল (শিরোনামের উপরে)')->maxLength(40);
        $offerPrice = Text::make('দাম, আগের দাম আর ছাড় পণ্য থেকে নিজে আসে।');

        return Builder::make('blocks')
            ->hiddenLabel()
            ->addActionLabel('ব্লক যোগ করুন')
            ->blockNumbers(false)
            ->collapsible()
            ->cloneable()
            ->blockPickerColumns(['default' => 1, 'sm' => 2, 'lg' => 3])
            ->blockPickerWidth('5xl')
            ->default(LandingTemplates::blocks(LandingTemplates::DEFAULT))
            ->blocks([
                Block::make('announcement')->label(self::labelFor('announcement'))->icon(Heroicon::OutlinedMegaphone)->maxItems(1)->schema([
                    self::enabled(),
                    TextInput::make('title')->label('লেখা')->required()->maxLength(120)->placeholder('ক্যাশ অন ডেলিভারি সারা দেশে')
                        ->helperText('পেজের একদম উপরে, লোগোর উপরে সরু রঙিন বারে দেখাবে।'),
                ]),
                Block::make('hero')->label(self::labelFor('hero'))->icon(Heroicon::OutlinedSparkles)->maxItems(1)->columns(2)->schema([
                    self::enabled(),
                    TextInput::make('badge')->label('ছোট লেবেল')->placeholder('সীমিত সময়ের অফার')->maxLength(40),
                    TextInput::make('button_label')->label('বাটনের লেখা')->placeholder('এখনই অর্ডার করুন')->maxLength(30),
                    $title('শিরোনাম (বড় লেখা)')->helperText('খালি রাখলে পণ্যের নাম')->columnSpanFull(),
                    $subtitle('সাবটাইটেল')->columnSpanFull(),
                    $image('image', 'ছবি (ঐচ্ছিক)')->helperText('খালি রাখলে পণ্যের ছবি; বর্গাকার বা ৪:৫ ভালো দেখায়'),
                    Grid::make(1)->columnSpan(1)->schema([
                        Toggle::make('show_price')->label('দাম দেখাবে')->default(true),
                        DateTimePicker::make('ends_at')->label('অফার শেষ (কাউন্টডাউন)')->seconds(false)->native(false)
                            ->helperText('খালি রাখলে কাউন্টডাউন দেখাবে না'),
                    ]),
                ]),
                Block::make('trust')->label(self::labelFor('trust'))->icon(Heroicon::OutlinedShieldCheck)->schema([
                    self::enabled(),
                    Repeater::make('items')->label('পয়েন্ট')->helperText('এক লাইনের ছোট স্ট্রিপ, সাধারণত হিরোর ঠিক নিচে।')->minItems(1)->maxItems(5)->reorderable()->columns(2)
                        ->itemLabel(fn (array $state) => $state['title'] ?? null)
                        ->schema([
                            Select::make('icon')->label('আইকন')->options(ManageHomepage::TRUST_ICONS)->default('badge')->required()->native(false),
                            TextInput::make('title')->label('লেখা')->required()->maxLength(40),
                        ]),
                ]),
                Block::make('offer')->label(self::labelFor('offer'))->icon(Heroicon::OutlinedTag)->columns(2)->schema([
                    self::enabled(),
                    TextInput::make('badge')->label('ছোট লেবেল')->placeholder('সীমিত সময়ের বিশেষ অফার')->maxLength(40),
                    TextInput::make('button_label')->label('বাটনের লেখা')->placeholder('এখনই অর্ডার করুন')->maxLength(40),
                    $title()->columnSpanFull(),
                    DateTimePicker::make('ends_at')->label('অফার শেষ (কাউন্টডাউন)')->seconds(false)->native(false)
                        ->helperText('খালি রাখলে কাউন্টডাউন দেখাবে না'),
                    $offerPrice,
                ]),
                Block::make('notice')->label(self::labelFor('notice'))->icon(Heroicon::OutlinedExclamationTriangle)->schema([
                    self::enabled(),
                    $title()->placeholder('বিশেষ দ্রষ্টব্য'),
                    Repeater::make('items')->label('লাইন')->helperText('নম্বর দিয়ে দেখাবে।')->minItems(1)->maxItems(8)->reorderable()
                        ->schema([TextInput::make('title')->hiddenLabel()->required()->maxLength(200)]),
                ]),
                Block::make('checklist')->label(self::labelFor('checklist'))->icon(Heroicon::OutlinedListBullet)->schema([
                    self::enabled(),
                    $eyebrow(),
                    $title()->placeholder('প্যাকেজে যা পাচ্ছেন'),
                    Repeater::make('items')->label('আইটেম')->helperText('বান্ডেল বা প্যাকেজের প্রতিটা জিনিস। একটাও না থাকলে এই অংশ সাইটে দেখাবে না।')->maxItems(12)->reorderable()->collapsible()->columns(2)
                        ->itemLabel(fn (array $state) => $state['title'] ?? null)
                        ->schema([
                            TextInput::make('title')->label('নাম')->required()->maxLength(100),
                            TextInput::make('note')->label('ছোট বিবরণ')->maxLength(200),
                            $image('image', 'ছবি (ঐচ্ছিক)')->columnSpanFull(),
                        ]),
                ]),
                Block::make('benefits')->label(self::labelFor('benefits'))->icon(Heroicon::OutlinedCheckBadge)->schema([
                    self::enabled(),
                    $eyebrow(),
                    $title(),
                    Repeater::make('items')->label('পয়েন্ট')->minItems(1)->maxItems(8)->reorderable()->collapsible()->columns(2)
                        ->itemLabel(fn (array $state) => $state['title'] ?? null)
                        ->schema([
                            Select::make('icon')->label('আইকন')->options(ManageHomepage::TRUST_ICONS)->default('badge')->required()->native(false)->columnSpanFull(),
                            TextInput::make('title')->label('লেখা')->required()->maxLength(80),
                            TextInput::make('note')->label('ছোট নোট')->maxLength(160),
                        ]),
                ]),
                Block::make('features')->label(self::labelFor('features'))->icon(Heroicon::OutlinedQueueList)->schema([
                    self::enabled(),
                    $eyebrow(),
                    $title(),
                    Repeater::make('items')->label('কার্ড')->minItems(1)->maxItems(6)->reorderable()->collapsible()
                        ->itemLabel(fn (array $state) => $state['title'] ?? null)
                        ->schema([
                            TextInput::make('title')->label('শিরোনাম')->required()->maxLength(80),
                            Textarea::make('text')->label('পয়েন্ট')->helperText('প্রতি লাইনে একটা পয়েন্ট')->rows(3)->maxLength(600),
                        ]),
                ]),
                Block::make('gallery')->label(self::labelFor('gallery'))->icon(Heroicon::OutlinedPhoto)->schema([
                    self::enabled(),
                    $eyebrow(),
                    $title(),
                    FileUpload::make('images')->label('ছবি')->image()->multiple()->reorderable()->maxFiles(12)
                        ->disk('public')->directory('landing')->maxSize(3072)->panelLayout('grid')
                        ->helperText('ছবি না দিলে এই অংশ সাইটে দেখাবে না।'),
                ]),
                Block::make('video')->label(self::labelFor('video'))->icon(Heroicon::OutlinedPlayCircle)->schema([
                    self::enabled(),
                    $title(),
                    TextInput::make('url')->label('ভিডিও লিংক')->url()->maxLength(500)
                        ->placeholder('https://www.youtube.com/watch?v=…')
                        ->helperText('YouTube বা Facebook ভিডিওর লিংক। খালি থাকলে এই অংশ সাইটে দেখাবে না।'),
                ]),
                Block::make('text')->label(self::labelFor('text'))->icon(Heroicon::OutlinedDocumentText)->schema([
                    self::enabled(),
                    $title(),
                    RichEditor::make('content')->label('লেখা'),
                ]),
                Block::make('reviews')->label(self::labelFor('reviews'))->icon(Heroicon::OutlinedChatBubbleLeftRight)->schema([
                    self::enabled(),
                    $eyebrow(),
                    $title(),
                    Repeater::make('items')->label('রিভিউ')->helperText('কাস্টমারের আসল রিভিউ দিন; একটাও না থাকলে এই অংশ সাইটে দেখাবে না।')->maxItems(12)->reorderable()->collapsible()->columns(2)
                        ->itemLabel(fn (array $state) => $state['name'] ?? null)
                        ->schema([
                            TextInput::make('name')->label('নাম')->required()->maxLength(60),
                            TextInput::make('location')->label('এলাকা')->maxLength(60),
                            Select::make('rating')->label('রেটিং')->options([5 => '★★★★★', 4 => '★★★★', 3 => '★★★'])->default(5)->required()->native(false),
                            $image('image', 'স্ক্রিনশট (ঐচ্ছিক)'),
                            Textarea::make('text')->label('রিভিউ')->rows(2)->maxLength(500)->columnSpanFull(),
                        ]),
                ]),
                Block::make('faq')->label(self::labelFor('faq'))->icon(Heroicon::OutlinedQuestionMarkCircle)->schema([
                    self::enabled(),
                    $eyebrow(),
                    $title(),
                    Repeater::make('items')->label('প্রশ্ন')->minItems(1)->maxItems(15)->reorderable()->collapsible()
                        ->itemLabel(fn (array $state) => $state['question'] ?? null)
                        ->schema([
                            TextInput::make('question')->label('প্রশ্ন')->required()->maxLength(200),
                            Textarea::make('answer')->label('উত্তর')->required()->rows(2)->maxLength(1000),
                        ]),
                ]),
                Block::make('cta')->label(self::labelFor('cta'))->icon(Heroicon::OutlinedCursorArrowRays)->columns(2)->schema([
                    self::enabled(),
                    $title(),
                    TextInput::make('button_label')->label('বাটনের লেখা')->placeholder('এখনই অর্ডার করুন')->maxLength(30),
                    $subtitle()->columnSpanFull(),
                    Text::make('বাটনে চাপলে অর্ডার ফর্মে নিয়ে যাবে।')->columnSpanFull(),
                ]),
                Block::make('call')->label(self::labelFor('call'))->icon(Heroicon::OutlinedPhone)->schema([
                    self::enabled(),
                    $eyebrow(),
                    $title()->placeholder('ফোনে অর্ডার করতে চান? সরাসরি কল করুন'),
                    $subtitle(),
                    Text::make('নম্বর আসে সেটিংসের হটলাইন থেকে; হটলাইন না থাকলে এই অংশ দেখাবে না।'),
                ]),
                Block::make('order_form')->label(self::labelFor('order_form'))->icon(Heroicon::OutlinedShoppingCart)->maxItems(1)->schema([
                    self::enabled(),
                    $eyebrow(),
                    $title()->placeholder('অর্ডার করতে নিচের ফর্মটি পূরণ করুন'),
                    $subtitle(),
                    Text::make('পণ্যের ভ্যারিয়েন্ট, পরিমাণ, ডেলিভারি চার্জ, অর্ডার বাম্প আর ক্যাশ অন ডেলিভারি — চেকআউটের মতোই কাজ করবে।'),
                    Repeater::make('packages')->label('প্যাকেজ (ঐচ্ছিক)')
                        ->helperText('যেমন "১ সেট" ১টা ৳৬৯৯, "২ সেট" ২টা ৳১,২৯৯। দিলে কাস্টমার পরিমাণের বদলে প্যাকেজ বাছবে; প্রথমটা আগে থেকে বাছা থাকবে। দাম পণ্যের সাধারণ দামের চেয়ে কম হলে পার্থক্যটা "প্যাকেজ ছাড়" হিসেবে বাদ যায়।')
                        ->maxItems(4)->reorderable()->collapsible()->columns(2)->defaultItems(0)
                        ->itemLabel(fn (array $state) => $state['label'] ?? null)
                        ->schema([
                            TextInput::make('label')->label('নাম')->required()->maxLength(80)->placeholder('২ সেট (স্পেশাল গিফট সহ)'),
                            TextInput::make('badge')->label('ছোট লেবেল')->maxLength(30)->placeholder('সেরা সেভিংস'),
                            TextInput::make('quantity')->label('কয়টা পণ্য')->integer()->minValue(1)->maxValue(20)->default(1)->required(),
                            Fields::money('price', 'প্যাকেজের মোট দাম')->required(),
                        ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Variants listed right under their main page.
            ->modifyQueryUsing(fn (Query $query) => $query->with(['product', 'parent'])
                ->orderByRaw('COALESCE(variant_of_id, id) desc')->orderByRaw('variant_of_id is not null')->orderBy('id')
                ->withCount(['orders' => fn (Query $q) => $q->where('status', '!=', OrderStatus::Cancelled)])
                ->withSum(['orders' => fn (Query $q) => $q->where('status', '!=', OrderStatus::Cancelled)], 'total'))
            ->columns([
                TextColumn::make('title')->label('পেজ')->searchable(['title', 'slug'])
                    ->description(fn (LandingPage $record) => $record->isVariant() ? '↳ ভ্যারিয়েন্ট · '.$record->traffic_weight.' ভাগ' : '/l/'.$record->slug)
                    ->extraAttributes(fn (LandingPage $record) => $record->isVariant() ? ['class' => 'ps-6'] : []),
                TextColumn::make('template')->label('ডিজাইন')->badge()->color('gray')
                    ->formatStateUsing(fn (?string $state) => LandingTemplates::options()[$state] ?? $state),
                TextColumn::make('views')->label('ভিউ')->numeric()->sortable(),
                TextColumn::make('product.name')->label('পণ্য')->placeholder('—'),
                TextColumn::make('orders_count')->label('অর্ডার')->numeric()->sortable()
                    ->url(fn (LandingPage $record) => route('filament.admin.resources.orders.index', ['filters' => ['landing_page_id' => ['value' => $record->id]]])),
                TextColumn::make('orders_sum_total')->label('বিক্রি')->formatStateUsing(fn ($state) => Money::format((int) $state))->placeholder('—')->sortable()
                    ->tooltip('বাতিল অর্ডার বাদে'),
                TextColumn::make('conversion')->label('কনভার্শন')->placeholder('—')
                    ->state(fn (LandingPage $record) => LandingPage::conversionRate((int) $record->orders_count, (int) $record->views))
                    ->formatStateUsing(fn (?float $state) => $state === null ? null : $state.'%')
                    ->tooltip('প্রতি ১০০ ভিউতে অর্ডার'),
                TextColumn::make('updated_at')->label('আপডেট')->since(),
                ToggleColumn::make('is_active')->label('প্রকাশিত'),
            ])
            ->recordActions([
                Action::make('view')->label('দেখুন')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (LandingPage $record) => self::url($record), shouldOpenInNewTab: true),
                Action::make('variant')->label('ভ্যারিয়েন্ট বানান')->icon(Heroicon::OutlinedBeaker)->color('gray')
                    ->visible(fn (LandingPage $record) => ! $record->isVariant())
                    ->authorize('create')
                    ->requiresConfirmation()
                    ->modalDescription('এই পেজের একটা কপি ভ্যারিয়েন্ট হিসেবে তৈরি হবে (বন্ধ অবস্থায়)। ব্লক বদলে প্রকাশ করলেই টেস্ট শুরু হবে।')
                    ->action(fn (LandingPage $record, $livewire) => $livewire->redirect(EditLandingPage::getUrl(['record' => self::makeVariant($record)]))),
                Action::make('promote')->label('জয়ী — মূল পেজে বসান')->icon(Heroicon::OutlinedTrophy)->color('success')
                    ->visible(fn (LandingPage $record) => $record->isVariant())
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalDescription('এই ভ্যারিয়েন্টের ব্লক, পণ্য আর SEO মূল পেজে কপি হবে আর ভ্যারিয়েন্টটি বন্ধ হবে। মূল লিংক একই থাকবে।')
                    ->action(function (LandingPage $record) {
                        self::promote($record);
                        Notification::make()->success()->title('মূল পেজ আপডেট হয়েছে')->send();
                    }),
                ReplicateAction::make()->label('কপি')
                    ->mutateRecordDataUsing(fn (array $data) => [...$data, 'title' => $data['title'].' (কপি)', 'slug' => $data['slug'].'-'.Str::lower(Str::random(4)), 'is_active' => false])
                    ->successRedirectUrl(fn (LandingPage $replica) => EditLandingPage::getUrl(['record' => $replica])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandingPages::route('/'),
            'create' => CreateLandingPage::route('/create'),
            'edit' => EditLandingPage::route('/{record}/edit'),
        ];
    }

    public static function makeVariant(LandingPage $page): LandingPage
    {
        $letter = chr(ord('B') + $page->variants()->count());

        $variant = $page->replicate(['views', 'orders_count', 'orders_sum_total'])->fill([
            'variant_of_id' => $page->id,
            'title' => $page->title.' — '.$letter,
            'slug' => Str::limit($page->slug, 90, '').'-'.Str::lower($letter).'-'.Str::lower(Str::random(3)),
            'is_active' => false,
            'is_indexable' => false,
            'traffic_weight' => 50,
        ]);
        $variant->save();

        return $variant;
    }

    public static function promote(LandingPage $variant): void
    {
        $variant->parent->update($variant->only(['product_id', 'template', 'blocks', 'meta_title', 'meta_description', 'og_image']));
        $variant->update(['is_active' => false]);
    }

    /** Builder state is keyed by item uuid. */
    private static function keyed(array $blocks): array
    {
        return collect($blocks)->mapWithKeys(fn (array $block) => [(string) Str::uuid() => $block])->all();
    }

    /** Preview card in the block picker (no state yet); "name · title" on a block already on the page. */
    private static function labelFor(string $type): \Closure
    {
        [$name, $description] = self::BLOCKS[$type];

        return function (?array $state) use ($type, $name, $description): string|HtmlString {
            if ($state === null) {
                return LandingPreview::block($type, $name, $description);
            }

            $title = $state['title'] ?? null;
            $hidden = ($state['enabled'] ?? true) ? '' : ' — লুকানো';

            return $name.($title ? ' · '.Str::limit($title, 50) : '').$hidden;
        };
    }

    private static function enabled(): Toggle
    {
        return Toggle::make('enabled')->label('দেখাবে')->default(true)->live()->columnSpanFull();
    }
}

<?php

namespace App\Filament\Resources\LandingPages;

use App\Domain\Marketing\Models\LandingPage;
use App\Domain\Order\Enums\OrderStatus;
use App\Filament\Pages\ManageHomepage;
use App\Filament\Resources\LandingPages\Pages\CreateLandingPage;
use App\Filament\Resources\LandingPages\Pages\EditLandingPage;
use App\Filament\Resources\LandingPages\Pages\ListLandingPages;
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

        return Builder::make('blocks')
            ->hiddenLabel()
            ->addActionLabel('ব্লক যোগ করুন')
            ->blockNumbers(false)
            ->collapsible()
            ->cloneable()
            ->blockPickerColumns(3)
            ->default(self::template())
            ->blocks([
                Block::make('hero')->label(self::labelFor('হিরো'))->icon(Heroicon::OutlinedSparkles)->maxItems(1)->columns(2)->schema([
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
                Block::make('benefits')->label(self::labelFor('সুবিধা / কেন কিনবেন'))->icon(Heroicon::OutlinedCheckBadge)->schema([
                    self::enabled(),
                    $title(),
                    Repeater::make('items')->label('পয়েন্ট')->minItems(1)->maxItems(8)->reorderable()->collapsible()->columns(2)
                        ->itemLabel(fn (array $state) => $state['title'] ?? null)
                        ->schema([
                            Select::make('icon')->label('আইকন')->options(ManageHomepage::TRUST_ICONS)->default('badge')->required()->native(false)->columnSpanFull(),
                            TextInput::make('title')->label('লেখা')->required()->maxLength(80),
                            TextInput::make('note')->label('ছোট নোট')->maxLength(160),
                        ]),
                ]),
                Block::make('gallery')->label(self::labelFor('ছবি গ্যালারি'))->icon(Heroicon::OutlinedPhoto)->schema([
                    self::enabled(),
                    $title(),
                    FileUpload::make('images')->label('ছবি')->image()->multiple()->reorderable()->maxFiles(12)
                        ->disk('public')->directory('landing')->maxSize(3072)->required()->panelLayout('grid'),
                ]),
                Block::make('video')->label(self::labelFor('ভিডিও'))->icon(Heroicon::OutlinedPlayCircle)->schema([
                    self::enabled(),
                    $title(),
                    TextInput::make('url')->label('ভিডিও লিংক')->url()->required()->maxLength(500)
                        ->placeholder('https://www.youtube.com/watch?v=…')
                        ->helperText('YouTube বা Facebook ভিডিওর লিংক'),
                ]),
                Block::make('text')->label(self::labelFor('লেখা'))->icon(Heroicon::OutlinedDocumentText)->schema([
                    self::enabled(),
                    $title(),
                    RichEditor::make('content')->label('লেখা')->required(),
                ]),
                Block::make('reviews')->label(self::labelFor('রিভিউ'))->icon(Heroicon::OutlinedChatBubbleLeftRight)->schema([
                    self::enabled(),
                    $title(),
                    Repeater::make('items')->label('রিভিউ')->minItems(1)->maxItems(12)->reorderable()->collapsible()->columns(2)
                        ->itemLabel(fn (array $state) => $state['name'] ?? null)
                        ->schema([
                            TextInput::make('name')->label('নাম')->required()->maxLength(60),
                            TextInput::make('location')->label('এলাকা')->maxLength(60),
                            Select::make('rating')->label('রেটিং')->options([5 => '★★★★★', 4 => '★★★★', 3 => '★★★'])->default(5)->required()->native(false),
                            $image('image', 'স্ক্রিনশট (ঐচ্ছিক)'),
                            Textarea::make('text')->label('রিভিউ')->rows(2)->maxLength(500)->columnSpanFull(),
                        ]),
                ]),
                Block::make('faq')->label(self::labelFor('প্রশ্ন ও উত্তর'))->icon(Heroicon::OutlinedQuestionMarkCircle)->schema([
                    self::enabled(),
                    $title(),
                    Repeater::make('items')->label('প্রশ্ন')->minItems(1)->maxItems(15)->reorderable()->collapsible()
                        ->itemLabel(fn (array $state) => $state['question'] ?? null)
                        ->schema([
                            TextInput::make('question')->label('প্রশ্ন')->required()->maxLength(200),
                            Textarea::make('answer')->label('উত্তর')->required()->rows(2)->maxLength(1000),
                        ]),
                ]),
                Block::make('cta')->label(self::labelFor('অর্ডার বাটন'))->icon(Heroicon::OutlinedCursorArrowRays)->columns(2)->schema([
                    self::enabled(),
                    $title(),
                    TextInput::make('button_label')->label('বাটনের লেখা')->placeholder('এখনই অর্ডার করুন')->maxLength(30),
                    $subtitle()->columnSpanFull(),
                    Text::make('বাটনে চাপলে অর্ডার ফর্মে নিয়ে যাবে।')->columnSpanFull(),
                ]),
                Block::make('order_form')->label(self::labelFor('অর্ডার ফর্ম'))->icon(Heroicon::OutlinedShoppingCart)->maxItems(1)->schema([
                    self::enabled(),
                    $title()->placeholder('অর্ডার করতে নিচের ফর্মটি পূরণ করুন'),
                    $subtitle(),
                    Text::make('পণ্যের ভ্যারিয়েন্ট, পরিমাণ, ডেলিভারি চার্জ, অর্ডার বাম্প আর ক্যাশ অন ডেলিভারি — চেকআউটের মতোই কাজ করবে।'),
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
        $variant->parent->update($variant->only(['product_id', 'blocks', 'meta_title', 'meta_description', 'og_image']));
        $variant->update(['is_active' => false]);
    }

    /** Starting layout for a new page: the usual ad-page flow, ready to fill in. */
    private static function template(): array
    {
        return [
            ['type' => 'hero', 'data' => ['enabled' => true, 'title' => '', 'show_price' => true, 'button_label' => 'এখনই অর্ডার করুন']],
            ['type' => 'benefits', 'data' => ['enabled' => true, 'title' => 'কেন এটা নেবেন', 'items' => [
                ['icon' => 'shield', 'title' => '১০০% অরিজিনাল পণ্য', 'note' => null],
                ['icon' => 'banknote', 'title' => 'পণ্য হাতে পেয়ে টাকা দিন', 'note' => null],
                ['icon' => 'truck', 'title' => 'সারা দেশে হোম ডেলিভারি', 'note' => null],
            ]]],
            ['type' => 'order_form', 'data' => ['enabled' => true, 'title' => 'অর্ডার করতে নিচের ফর্মটি পূরণ করুন']],
            ['type' => 'faq', 'data' => ['enabled' => true, 'title' => 'সাধারণ প্রশ্ন', 'items' => [
                ['question' => 'ডেলিভারি পেতে কত দিন লাগে?', 'answer' => 'ঢাকার ভেতরে ১–২ দিন, ঢাকার বাইরে ২–৪ দিন।'],
                ['question' => 'পণ্য দেখে টাকা দেওয়া যাবে?', 'answer' => 'হ্যাঁ, ডেলিভারি ম্যানের সামনে পণ্য দেখে টাকা পরিশোধ করবেন।'],
            ]]],
        ];
    }

    private static function labelFor(string $type): \Closure
    {
        return function (?array $state) use ($type): string {
            $title = $state['title'] ?? null;
            $hidden = ($state['enabled'] ?? true) ? '' : ' — লুকানো';

            return $type.($title ? ' · '.Str::limit($title, 50) : '').$hidden;
        };
    }

    private static function enabled(): Toggle
    {
        return Toggle::make('enabled')->label('দেখাবে')->default(true)->live()->columnSpanFull();
    }
}

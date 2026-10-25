<?php

namespace App\Filament\Pages;

use App\Domain\Catalog\Enums\ProductBadge;
use App\Domain\Catalog\Models\Category;
use App\Domain\Content\Support\HomeSections;
use App\Settings\HomeSettings;
use App\Settings\SeasonalSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Drag to reorder, toggle to hide, edit copy and product rules for every home page section. */
class ManageHomepage extends Page
{
    use HasPageShield;

    public const TRUST_ICONS = [
        'truck' => 'ট্রাক (ডেলিভারি)', 'banknote' => 'টাকা (COD)', 'rotate' => 'রিটার্ন', 'shield' => 'শিল্ড (অরিজিনাল)',
        'gift' => 'গিফট', 'phone' => 'ফোন', 'star' => 'স্টার', 'clock' => 'ঘড়ি', 'badge' => 'ব্যাজ (চেক)', 'package' => 'প্যাকেট',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'কনটেন্ট';

    protected static ?int $navigationSort = -1;

    protected static ?string $navigationLabel = 'হোমপেজ বিল্ডার';

    protected static ?string $title = 'হোমপেজ বিল্ডার';

    protected static ?string $slug = 'homepage';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['sections' => app(HomeSettings::class)->sections]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Text::make('উপর-নিচে টেনে সেকশনের ক্রম বদলান, টগল দিয়ে লুকান, "+ সেকশন যোগ করুন" দিয়ে নতুন সেকশন দিন। English ঘর খালি রাখলে বাংলাটাই দেখাবে। হিরো আর প্রোমো ব্যানারের ছবি "ব্যানার" পেজ থেকে আসে।'),
                Builder::make('sections')
                    ->hiddenLabel()
                    ->addActionLabel('সেকশন যোগ করুন')
                    ->blockNumbers(false)
                    ->collapsible()
                    ->collapsed()
                    ->cloneable()
                    ->blockPickerColumns(2)
                    ->blocks([
                        Block::make('hero')->label($this->labelFor('হিরো / ব্যানার স্লাইডার'))->icon(Heroicon::OutlinedPhoto)->maxItems(1)->schema([
                            $this->enabled(),
                            Text::make('চালু থাকা হিরো ব্যানার থাকলে স্লাইডার দেখাবে; না থাকলে নিচের লেখা আর দুটো বাছাই করা পণ্যের ছবি।'),
                            ...$this->pair('eyebrow', 'ছোট লেবেল', 40),
                            ...$this->pair('title', 'শিরোনাম', 80),
                            ...$this->pair('subtitle', 'সাবটাইটেল', 240, textarea: true),
                            ...$this->pair('button_label', 'বাটনের লেখা', 30),
                            TextInput::make('button_url')->label('বাটনের লিংক')->placeholder('/shop')->maxLength(255),
                        ])->columns(2),
                        Block::make('trust')->label($this->labelFor('ভরসার স্ট্রিপ (ডেলিভারি, COD…)'))->icon(Heroicon::OutlinedShieldCheck)->schema([
                            $this->enabled(),
                            Repeater::make('items')->label('আইটেম')->minItems(1)->maxItems(4)->reorderable()->collapsible()->columns(2)
                                ->itemLabel(fn (array $state) => $state['title'] ?? null)
                                ->schema([
                                    Select::make('icon')->label('আইকন')->options(self::TRUST_ICONS)->required()->native(false)->columnSpanFull(),
                                    ...$this->pair('title', 'লেখা', 40, required: true),
                                    ...$this->pair('note', 'ছোট নোট', 60),
                                ]),
                        ]),
                        Block::make('categories')->label($this->labelFor('ক্যাটাগরি রেল'))->icon(Heroicon::OutlinedRectangleGroup)->maxItems(1)->schema([
                            $this->enabled(),
                            ...$this->pair('title', 'শিরোনাম', 80),
                            TextInput::make('limit')->label('কয়টা ক্যাটাগরি')->numeric()->integer()->minValue(1)->maxValue(14)->default(7)->required()
                                ->helperText('মেইন ক্যাটাগরি, "ফিচার্ড" গুলো আগে'),
                        ])->columns(2),
                        Block::make('products')->label($this->labelFor('প্রোডাক্ট সারি'))->icon(Heroicon::OutlinedShoppingBag)->schema([
                            $this->enabled(),
                            ...$this->pair('title', 'শিরোনাম', 80, required: true),
                            ...$this->pair('note', 'সাবটাইটেল', 160),
                            Select::make('source')->label('কোন পণ্য')->options(HomeSections::SOURCES)->default('new')->required()->native(false)->live(),
                            Select::make('category_id')->label('ক্যাটাগরি')->options(fn () => Category::orderBy('name')->pluck('name', 'id'))->searchable()
                                ->visible(fn (Get $get) => $get('source') === 'category')->required(fn (Get $get) => $get('source') === 'category')
                                ->helperText('সাব-ক্যাটাগরির পণ্যও আসবে'),
                            TextInput::make('limit')->label('কয়টা পণ্য')->numeric()->integer()->minValue(1)->maxValue(20)->default(8)->required(),
                            Select::make('layout')->label('লেআউট')->options(['grid' => 'গ্রিড', 'carousel' => 'স্লাইডার (ক্যারোসেল)'])->default('grid')->required()->native(false)->live(),
                            Select::make('columns')->label('প্রতি সারিতে (ডেস্কটপ)')->options(['4' => '৪টা', '5' => '৫টা'])->default('4')->required()->native(false)
                                ->visible(fn (Get $get) => $get('layout') === 'grid'),
                            Select::make('style')->label('স্টাইল')->options(['plain' => 'সাধারণ', 'panel' => 'ধূসর প্যানেল', 'highlight' => 'প্রাইমারি রঙের প্যানেল (অফারের জন্য)'])->default('plain')->required()->native(false),
                            Select::make('badge')->label('সব কার্ডে ব্যাজ')->options(collect(ProductBadge::cases())->mapWithKeys(fn (ProductBadge $b) => [$b->value => $b->getLabel()]))->placeholder('প্রোডাক্টের নিজের ব্যাজ'),
                            TextInput::make('link_url')->label('"সব দেখুন" লিংক')->placeholder('/shop?sort=newest')->maxLength(255)->helperText('খালি রাখলে লিংক দেখাবে না'),
                            Text::make('উপরের সেকশনে দেখানো পণ্য এখানে আর আসবে না — যাতে একই পণ্য বারবার না দেখায়। পণ্য না থাকলে সেকশনটা নিজে থেকেই লুকিয়ে যাবে।')->columnSpanFull(),
                        ])->columns(2),
                        Block::make('promo_banners')->label($this->labelFor('প্রোমো ব্যানার'))->icon(Heroicon::OutlinedMegaphone)->maxItems(1)->schema([
                            $this->enabled(),
                            Text::make('"ব্যানার" পেজের প্রোমো প্লেসমেন্টের ব্যানারগুলো এখানে দেখাবে।'),
                        ]),
                        Block::make('help')->label($this->labelFor('সাহায্য / কল করুন'))->icon(Heroicon::OutlinedPhone)->maxItems(1)->schema([
                            $this->enabled(),
                            ...$this->pair('title', 'শিরোনাম', 80),
                            ...$this->pair('note', 'লেখা', 200, textarea: true),
                            Text::make('হটলাইন আর WhatsApp নম্বর "সাইট সেটিংস" থেকে আসে।')->columnSpanFull(),
                        ])->columns(2),
                        Block::make('prayer_times')->label($this->labelFor('নামাজের সময়'))->icon(Heroicon::OutlinedClock)->maxItems(1)->schema([
                            $this->enabled(),
                            ...$this->pair('title', 'শিরোনাম', 80),
                            Select::make('city')->label('শহর')->options(SeasonalSettings::PRAYER_CITIES)->placeholder('সাইটের ডিফল্ট শহর')->native(false),
                            Text::make('কাস্টমার নিজের শহর বেছে নিতে পারেন। রমজান মোড চালু থাকলে সেহরি আর ইফতারের কাউন্টডাউন দেখায় (কনটেন্ট → রমজান ও নামাজের সময়)।')->columnSpanFull(),
                        ])->columns(2),
                        Block::make('text')->label($this->labelFor('লেখা / কাস্টম কনটেন্ট'))->icon(Heroicon::OutlinedDocumentText)->schema([
                            $this->enabled(),
                            ...$this->pair('title', 'শিরোনাম', 80),
                            RichEditor::make('content')->label('লেখা (বাংলা)')->columnSpanFull(),
                            RichEditor::make('content_en')->label('লেখা (English)')->columnSpanFull(),
                        ])->columns(2),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('সেভ করুন')->submit('save')->keyBindings(['mod+s']),
                        Action::make('preview')->label('হোমপেজ দেখুন')->color('gray')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->url(config('shop.storefront_url'), shouldOpenInNewTab: true),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        // Builder state is keyed by uuid; store a plain ordered list.
        $sections = array_values($this->form->getState()['sections'] ?? []);

        $settings = app(HomeSettings::class);
        $settings->sections = $sections;
        $settings->save();

        Notification::make()->success()->title('সেভ হয়েছে')->body('হোমপেজে কয়েক সেকেন্ডের মধ্যে দেখাবে।')->send();
    }

    /** Block header: type, then its title, then "(লুকানো)" when switched off. */
    private function labelFor(string $type): \Closure
    {
        return function (?array $state) use ($type): string {
            $title = $state['title'] ?? null;
            $hidden = ($state['enabled'] ?? true) ? '' : ' — লুকানো';

            return $type.($title ? " · {$title}" : '').$hidden;
        };
    }

    private function enabled(): Toggle
    {
        return Toggle::make('enabled')->label('দেখাবে')->default(true)->live()->columnSpanFull();
    }

    /** @return array{0: TextInput|Textarea, 1: TextInput|Textarea} */
    private function pair(string $name, string $label, int $max, bool $textarea = false, bool $required = false): array
    {
        $make = fn (string $field, string $suffix) => ($textarea ? Textarea::make($field)->rows(2) : TextInput::make($field))
            ->label("{$label} ({$suffix})")->maxLength($max);

        return [$make($name, 'বাংলা')->required($required), $make("{$name}_en", 'English')];
    }
}

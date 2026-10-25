<?php

namespace App\Filament\Pages;

use App\Settings\BrandSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Brand name, logo, favicon, colours, fonts and corner radius. The whole storefront reads these. */
class ManageBrandSettings extends Page
{
    use HasPageShield;

    /** The approved design tokens; "ডিফল্টে ফেরত যান" restores these. */
    public const DEFAULT_THEME = [
        'color_primary' => '#0F4C3A',
        'color_accent' => '#B8975A',
        'color_background' => '#F8F8F6',
        'color_surface' => '#FFFFFF',
        'color_text' => '#171717',
        'color_muted' => '#6B6B68',
        'color_border' => '#E6E6E2',
        'radius' => 14,
        'font_heading' => 'fraunces',
        'font_body' => 'manrope',
        'font_heading_bn' => 'noto-serif-bengali',
        'font_body_bn' => 'noto-sans-bengali',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?int $navigationSort = -1;

    protected static ?string $navigationLabel = 'ব্র্যান্ড ও থিম';

    protected static ?string $title = 'ব্র্যান্ড ও থিম';

    protected static ?string $slug = 'settings/brand';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(BrandSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        $color = fn (string $name, string $label, string $help) => ColorPicker::make($name)->label($label)->helperText($help)->required()
            ->regex('/^#[0-9A-Fa-f]{6}$/')->validationMessages(['regex' => '#RRGGBB ফরম্যাটে দিন']);

        return $schema
            ->statePath('data')
            ->components([
                Section::make('ব্র্যান্ড')->description('হেডার, ফুটার, ব্রাউজার ট্যাব, ইনভয়েস আর অ্যাডমিনে দেখাবে')->schema([
                    Grid::make(3)->schema([
                        TextInput::make('name')->label('ব্র্যান্ডের নাম')->required()->maxLength(40)
                            ->helperText('লোগো না থাকলে এই নামটাই লোগো হিসেবে দেখাবে; শেষ শব্দটা প্রাইমারি রঙে।'),
                        TextInput::make('tagline')->label('ট্যাগলাইন (বাংলা)')->maxLength(120)->helperText('হোমপেজের ব্রাউজার টাইটেলে'),
                        TextInput::make('tagline_en')->label('ট্যাগলাইন (English)')->maxLength(120),
                    ]),
                    Grid::make(2)->schema([
                        FileUpload::make('logo')->label('লোগো')->image()->disk('public')->directory('brand')->maxSize(1024)
                            ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/webp', 'image/jpeg'])
                            ->helperText('আনুভূমিক লোগো, স্বচ্ছ ব্যাকগ্রাউন্ড (PNG/SVG/WebP)। ৪০ পিক্সেল উচ্চতায় দেখাবে।'),
                        FileUpload::make('favicon')->label('ফেভিকন')->image()->disk('public')->directory('brand')->maxSize(512)
                            ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'])
                            ->helperText('বর্গাকার, অন্তত ৯৬×৯৬ পিক্সেল PNG বা SVG।'),
                    ]),
                ]),
                Section::make('রং')->description('পুরো সাইটের রং এখান থেকে আসে। লেখা আর ব্যাকগ্রাউন্ডের মধ্যে যথেষ্ট কনট্রাস্ট রাখুন।')
                    ->headerActions([
                        Action::make('resetTheme')->label('ডিফল্টে ফেরত যান')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                            ->requiresConfirmation()->modalDescription('রং, রেডিয়াস আর ফন্ট অনুমোদিত ডিজাইনে ফিরে যাবে (সেভ করার পর)।')
                            ->action(fn () => $this->form->fill([...$this->data, ...self::DEFAULT_THEME])),
                    ])
                    ->schema([
                        Grid::make(4)->schema([
                            $color('color_primary', 'প্রাইমারি', 'বাটন, লিংক, অফার প্যানেল'),
                            $color('color_accent', 'অ্যাকসেন্ট', 'বেস্ট সেলার ব্যাজ, হাইলাইট — অল্প জায়গায়'),
                            $color('color_background', 'ব্যাকগ্রাউন্ড', 'পেজের পেছনের রং'),
                            $color('color_surface', 'কার্ড', 'প্রোডাক্ট কার্ড, প্যানেল'),
                            $color('color_text', 'লেখা', 'মূল লেখার রং'),
                            $color('color_muted', 'হালকা লেখা', 'নোট, সাবটাইটেল'),
                            $color('color_border', 'বর্ডার', 'কার্ড আর ইনপুটের দাগ'),
                            TextInput::make('radius')->label('কোণের গোলাকৃতি')->numeric()->integer()->minValue(0)->maxValue(28)->suffix('px')->required()
                                ->helperText('০ = চৌকো, ১৪ = ডিফল্ট'),
                        ]),
                    ]),
                Section::make('ফন্ট')->description('প্রতিটা ভাষার জন্য আলাদা ফন্ট — বাংলা লেখায় বাংলা ফন্ট, ইংরেজিতে ইংরেজি ফন্ট বসে।')->schema([
                    Grid::make(2)->schema([
                        Select::make('font_heading')->label('হেডিং (English)')->options(BrandSettings::HEADING_FONTS)->required()->native(false),
                        Select::make('font_heading_bn')->label('হেডিং (বাংলা)')->options(BrandSettings::HEADING_FONTS_BN)->required()->native(false),
                        Select::make('font_body')->label('বডি (English)')->options(BrandSettings::BODY_FONTS)->required()->native(false),
                        Select::make('font_body_bn')->label('বডি (বাংলা)')->options(BrandSettings::BODY_FONTS_BN)->required()->native(false),
                    ]),
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
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        foreach ($state as $key => $value) {
            if (str_starts_with($key, 'color_')) {
                $state[$key] = strtoupper($value);
            }
        }

        app(BrandSettings::class)->fill($state)->save();

        Notification::make()->success()->title('সেভ হয়েছে')->body('স্টোরফ্রন্টে কয়েক সেকেন্ডের মধ্যে দেখাবে।')->send();
    }
}

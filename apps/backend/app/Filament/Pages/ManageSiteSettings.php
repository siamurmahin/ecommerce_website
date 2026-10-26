<?php

namespace App\Filament\Pages;

use App\Settings\SiteSettings;
use App\Support\Phone;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Store contact details, top bar and footer copy, social links. Everything here drives the storefront. */
class ManageSiteSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'সাইট সেটিংস';

    protected static ?string $title = 'সাইট সেটিংস';

    protected static ?string $slug = 'settings/site';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(SiteSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('যোগাযোগ')->description('হেডার, মোবাইল মেনু, প্রোডাক্ট পেজ, ইনভয়েস আর ফুটারে দেখাবে')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('hotline')->label('হটলাইন')->tel()->required()->maxLength(30)->helperText('যেভাবে দেখাতে চান, যেমন 01700-000000'),
                        TextInput::make('whatsapp')->label('WhatsApp নম্বর')->tel()->maxLength(20)->helperText('খালি রাখলে WhatsApp বাটন লুকানো থাকবে')
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Phone::normalize($state) : null)
                            ->rule(fn () => fn (string $attribute, $value, $fail) => filled($value) && ! preg_match(Phone::PATTERN, Phone::normalize($value)) ? $fail('সঠিক বাংলাদেশি মোবাইল নম্বর দিন') : null),
                        TextInput::make('email')->label('ইমেইল')->email()->maxLength(120),
                    ]),
                    Grid::make(2)->schema([
                        Textarea::make('address')->label('ঠিকানা (বাংলা)')->rows(2)->maxLength(300),
                        Textarea::make('address_en')->label('ঠিকানা (English)')->rows(2)->maxLength(300),
                    ]),
                ]),
                Section::make('টপ বার ও ফুটার')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('announcement')->label('টপ বারের লেখা (বাংলা)')->helperText('হেডারের উপরের সবুজ বার; খালি রাখলে বারটা লুকানো থাকবে')->maxLength(120),
                        TextInput::make('announcement_en')->label('টপ বারের লেখা (English)')->maxLength(120),
                        Textarea::make('footer_about')->label('ফুটারের পরিচিতি (বাংলা)')->rows(3)->maxLength(300),
                        Textarea::make('footer_about_en')->label('ফুটারের পরিচিতি (English)')->rows(3)->maxLength(300),
                        Toggle::make('show_hijri_date')->label('টপ বারে আজকের হিজরি তারিখ দেখান')->live(),
                        Select::make('hijri_offset')->label('হিজরি তারিখ সমন্বয়')->native(false)->selectablePlaceholder(false)
                            ->options([-2 => '২ দিন আগে', -1 => '১ দিন আগে', 0 => 'যেমন হিসাব হয়', 1 => '১ দিন পরে', 2 => '২ দিন পরে'])
                            ->helperText('বাংলাদেশে চাঁদ দেখা অনুযায়ী তারিখ আলাদা হলে এখানে মিলিয়ে নিন (সাধারণত ১ দিন আগে)।')
                            ->visible(fn (Get $get) => $get('show_hijri_date')),
                    ]),
                ]),
                Section::make('সোশ্যাল মিডিয়া')->description('খালি রাখলে আইকন দেখাবে না')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('facebook_url')->label('Facebook পেজ')->url()->maxLength(255)->placeholder('https://facebook.com/…'),
                        TextInput::make('instagram_url')->label('Instagram')->url()->maxLength(255)->placeholder('https://instagram.com/…'),
                        TextInput::make('youtube_url')->label('YouTube')->url()->maxLength(255)->placeholder('https://youtube.com/@…'),
                        TextInput::make('tiktok_url')->label('TikTok')->url()->maxLength(255)->placeholder('https://tiktok.com/@…'),
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
        $settings = app(SiteSettings::class);
        $settings->fill($this->form->getState())->save();

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }
}

<?php

namespace App\Filament\Pages;

use App\Settings\SeasonalSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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

/** Ramadan mode (top-bar message, Sehri/Iftar countdown) and prayer-time defaults. */
class ManageSeasonalSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMoon;

    protected static string|UnitEnum|null $navigationGroup = 'কনটেন্ট';

    protected static ?string $navigationLabel = 'রমজান ও নামাজের সময়';

    protected static ?string $title = 'রমজান ও নামাজের সময়';

    protected static ?string $slug = 'seasonal';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(SeasonalSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('রমজান মোড')
                    ->description('তারিখের মধ্যে থাকলে সাইটের উপরের ঘোষণার জায়গায় রমজানের বার্তা দেখায়, আর হোমপেজের নামাজের সময় ব্লকে সেহরি ও ইফতারের কাউন্টডাউন আসে। শুরু ও শেষের দিন চাঁদ দেখার উপর নির্ভর করে, তাই নিজে বসিয়ে দিন।')
                    ->schema([
                        Toggle::make('ramadan_enabled')->label('রমজান মোড চালু')->live(),
                        Grid::make(2)->visible(fn (Get $get) => (bool) $get('ramadan_enabled'))->schema([
                            DatePicker::make('ramadan_starts_on')->label('প্রথম রোজা')->native(false)->displayFormat('d M Y')->required(),
                            DatePicker::make('ramadan_ends_on')->label('শেষ রোজা')->native(false)->displayFormat('d M Y')->afterOrEqual('ramadan_starts_on')->required(),
                            TextInput::make('ramadan_announcement')->label('উপরের বার্তা (বাংলা)')->maxLength(160)->helperText('খালি রাখলে সাধারণ ঘোষণাই থাকবে'),
                            TextInput::make('ramadan_announcement_en')->label('উপরের বার্তা (English)')->maxLength(160),
                        ]),
                    ]),
                Section::make('নামাজের সময়')
                    ->description('সময় হিসাব হয় শহরের অবস্থান থেকে (করাচি পদ্ধতি, হানাফি আসর) — ইসলামিক ফাউন্ডেশনের ক্যালেন্ডারের সাথে ১–২ মিনিট এদিক-ওদিক হতে পারে, দরকারে মিনিট সমন্বয় দিন। হোমপেজে দেখাতে হোমপেজ বিল্ডারে "নামাজের সময়" ব্লক যোগ করুন।')
                    ->columns(2)
                    ->schema([
                        Select::make('prayer_city')->label('ডিফল্ট শহর')->options(SeasonalSettings::PRAYER_CITIES)->native(false)->required(),
                        TextInput::make('prayer_offset')->label('মিনিট সমন্বয়')->numeric()->integer()->minValue(-15)->maxValue(15)->suffix('মিনিট')->required(),
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
        $settings = app(SeasonalSettings::class);

        // Hidden fields aren't in the state; keep their stored values.
        foreach ($this->form->getState() as $key => $value) {
            $settings->{$key} = $key === 'prayer_offset' ? (int) $value : $value;
        }
        $settings->save();

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }
}

<?php

namespace App\Filament\Pages;

use App\Filament\Support\Fields;
use App\Settings\GiftSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
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

/** Gift wrap (price and what it costs the shop) and a gift note at checkout. */
class ManageGiftSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'গিফট র‍্যাপ';

    protected static ?string $title = 'গিফট র‍্যাপ ও গিফট নোট';

    protected static ?string $slug = 'settings/gift';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(GiftSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('গিফট র‍্যাপ')->schema([
                    Toggle::make('wrap_enabled')->label('চেকআউটে "গিফট র‍্যাপ করে দিন" অপশন দেখান')->live(),
                    Grid::make(2)->visible(fn (Get $get) => $get('wrap_enabled'))->schema([
                        Fields::money('wrap_price', 'কাস্টমারের কাছ থেকে নেবেন')->required()->minValue(0)->helperText('০ দিলে ফ্রি গিফট র‍্যাপ।'),
                        Fields::money('wrap_cost', 'দোকানের খরচ (প্রতি অর্ডার)')->required()->minValue(0)->helperText('শুধু লাভ-ক্ষতির হিসাবে লাগে।'),
                    ]),
                ]),
                Section::make('গিফট নোট')->schema([
                    Toggle::make('note_enabled')->label('কাস্টমার কার্ডে লেখার জন্য ছোট বার্তা দিতে পারবেন')
                        ->helperText('প্যাকিং স্লিপে ছাপা হবে, যাতে প্যাক করার সময় কার্ডে লিখে দেওয়া যায়।'),
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
        $settings = app(GiftSettings::class);
        $settings->fill($this->form->getState());
        $settings->save();

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }
}

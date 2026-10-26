<?php

namespace App\Filament\Pages;

use App\Domain\WhatsApp\WhatsAppSender;
use App\Settings\SmsSettings;
use App\Settings\WhatsAppSettings;
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
use Throwable;
use UnitEnum;

/** WhatsApp Business account, admin alert numbers and the approved template behind each order event. */
class ManageWhatsAppSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'WhatsApp';

    protected static ?string $title = 'WhatsApp অর্ডার আপডেট';

    protected static ?string $slug = 'settings/whatsapp';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(app(WhatsAppSettings::class)->toArray())->except('access_token')->all());
    }

    public function form(Schema $schema): Schema
    {
        $settings = app(WhatsAppSettings::class);
        $gateways = collect(config('whatsapp.gateways'))->map(fn (string $class) => $class::label())->all();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('অ্যাকাউন্ট')
                    ->description('Meta Business Manager-এ WhatsApp Business অ্যাকাউন্ট খুলে "Phone number ID" আর একটা স্থায়ী access token নিন। অর্ডারের মেসেজ SMS-এর পাশাপাশি যাবে।')
                    ->schema([
                        Toggle::make('enabled')->label('WhatsApp আপডেট চালু')->helperText('বন্ধ থাকলে কোনো WhatsApp মেসেজ যাবে না।')->columnSpanFull(),
                        Grid::make(3)->schema([
                            Select::make('gateway')->label('মাধ্যম')->options($gateways)->required()->native(false)->live()
                                ->helperText('টেস্ট মোডে মেসেজ না পাঠিয়ে লগে লেখা হয়।'),
                            TextInput::make('phone_number_id')->label('Phone number ID')->maxLength(40)
                                ->hidden(fn (Get $get) => $get('gateway') === 'log'),
                            TextInput::make('access_token')->label('Access token')->password()->revealable()->autocomplete('off')->maxLength(500)
                                ->placeholder(filled($settings->access_token) ? '•••••••• সেভ করা আছে — বদলাতে নতুনটা দিন' : null)
                                ->hidden(fn (Get $get) => $get('gateway') === 'log'),
                        ]),
                        TextInput::make('admin_phones')->label('নতুন অর্ডারের অ্যালার্ট যাবে যে নম্বরে')->maxLength(200)
                            ->placeholder('01711000000, 01811000000')->helperText('কমা দিয়ে একাধিক নম্বর'),
                        Actions::make([
                            Action::make('test')->label('টেস্ট মেসেজ পাঠান (hello_world)')->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                                ->schema([TextInput::make('phone')->label('মোবাইল নম্বর')->tel()->required()])
                                ->action(fn (array $data) => $this->sendTest($data['phone'])),
                        ]),
                    ]),
                Section::make('মেসেজ টেমপ্লেট')
                    ->description('WhatsApp-এ দোকান থেকে প্রথম মেসেজ পাঠাতে Meta-র অনুমোদিত টেমপ্লেট লাগে। Meta-তে টেমপ্লেট বানানোর সময় নিচের লেখাটাই দিন, শুধু {name} {order}… এর জায়গায় পরপর {{1}} {{2}}… বসান। এখানে দিন সেই টেমপ্লেটের নাম আর ভাষা। যা বসাতে পারবেন: '.implode('  ', SmsSettings::PLACEHOLDERS))
                    ->schema(collect(WhatsAppSettings::EVENTS)->map(fn (string $label, string $key) => Grid::make(['default' => 1, 'md' => 6])->schema([
                        Toggle::make("templates.{$key}.enabled")->label($label)->inline(false)->columnSpan(['md' => 2]),
                        TextInput::make("templates.{$key}.template")->label('টেমপ্লেটের নাম')->maxLength(100)->columnSpan(['md' => 2])
                            ->rule('regex:/^[a-z0-9_]+$/')->validationMessages(['regex' => 'ছোট হাতের ইংরেজি, সংখ্যা আর _ দিয়ে (Meta-র নিয়ম)।'])
                            ->required(fn (Get $get) => (bool) $get("templates.{$key}.enabled")),
                        Select::make("templates.{$key}.language")->label('ভাষা')->options(['bn' => 'বাংলা (bn)', 'en' => 'English (en)', 'en_US' => 'English US (en_US)'])
                            ->native(false)->selectablePlaceholder(false)->default('bn')->columnSpan(['md' => 2]),
                        Textarea::make("templates.{$key}.body")->label('লেখা')->rows(2)->maxLength(1000)->columnSpanFull()
                            ->required(fn (Get $get) => (bool) $get("templates.{$key}.enabled")),
                    ]))->values()->all()),
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
        if (blank($state['access_token'] ?? null)) {
            unset($state['access_token']);
        }

        $settings = app(WhatsAppSettings::class);
        $settings->fill($state)->save();
        $this->form->fill(collect($settings->toArray())->except('access_token')->all());

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }

    public function sendTest(string $phone): void
    {
        $phone = Phone::normalize($phone);
        if (! preg_match(Phone::PATTERN, (string) $phone)) {
            Notification::make()->danger()->title('সঠিক মোবাইল নম্বর দিন')->send();

            return;
        }

        try {
            // Meta's ready-made sample template; sent directly so the result shows up here.
            app(WhatsAppSender::class)->gateway()->send($phone, 'hello_world', 'en_US', []);
            Notification::make()->success()->title('পাঠানো হয়েছে')->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title('পাঠানো যায়নি')->body($e->getMessage())->send();
        }
    }
}

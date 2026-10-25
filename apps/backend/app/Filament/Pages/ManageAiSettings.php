<?php

namespace App\Filament\Pages;

use App\Settings\AiSettings;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** API key and house style for "AI দিয়ে লিখুন" on the product form. */
class ManageAiSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'AI লেখা';

    protected static ?string $title = 'AI দিয়ে প্রোডাক্টের বিবরণ';

    protected static ?string $slug = 'ai';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(app(AiSettings::class)->toArray())->except('api_key')->all());
    }

    public function form(Schema $schema): Schema
    {
        $stored = filled(app(AiSettings::class)->api_key);

        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->description('চালু করলে পণ্যের ফর্মে "AI দিয়ে লিখুন" বাটন আসে: নাম, ক্যাটাগরি, দাম আর স্পেসিফিকেশন দেখে বাংলা ও ইংরেজি বিবরণ, হাইলাইট আর SEO লিখে দেয়। সেভ করার আগে আপনি দেখে ঠিক করে নেবেন। খরচ Anthropic অ্যাকাউন্ট থেকে কাটে — একটা পণ্যে সাধারণত কয়েক টাকা।')
                    ->columns(2)
                    ->schema([
                        Toggle::make('enabled')->label('AI লেখা চালু')->columnSpanFull(),
                        TextInput::make('api_key')->label('Anthropic API key')->password()->revealable()->autocomplete('off')->maxLength(300)
                            ->placeholder($stored ? '•••••••• সেভ করা আছে — বদলাতে নতুনটা দিন' : 'sk-ant-…')
                            ->helperText('কোথায় পাবেন: platform.claude.com → API keys → Create key। Key শুধু সার্ভারে এনক্রিপ্ট করে রাখা হয়।')
                            ->hintAction($stored ? Action::make('clearKey')->label('মুছে ফেলুন')->color('danger')->requiresConfirmation()->action(function () {
                                $settings = app(AiSettings::class);
                                $settings->api_key = null;
                                $settings->save();
                                Notification::make()->success()->title('মুছে ফেলা হয়েছে')->send();
                            }) : null),
                        Select::make('model')->label('মডেল')->options(AiSettings::MODELS)->native(false)->required(),
                        Textarea::make('brand_voice')->label('লেখার ধরন (ঐচ্ছিক)')->rows(4)->maxLength(1500)->columnSpanFull()
                            ->placeholder('যেমন: "আপনি" সম্বোধন করবেন, ছোট বাক্য, "প্রিমিয়াম" শব্দটা কম ব্যবহার করবেন।'),
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
        if (blank($state['api_key'] ?? null)) {
            unset($state['api_key']);
        }

        $settings = app(AiSettings::class);
        $settings->fill($state)->save();
        $this->form->fill(collect($settings->toArray())->except('api_key')->all());

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }
}

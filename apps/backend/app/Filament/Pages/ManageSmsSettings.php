<?php

namespace App\Filament\Pages;

use App\Domain\Sms\SmsSender;
use App\Settings\SmsSettings;
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

/** Gateway, credentials, admin alert numbers and the order message templates. */
class ManageSmsSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'SMS';

    protected static ?string $title = 'SMS সেটিংস';

    protected static ?string $slug = 'settings/sms';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(app(SmsSettings::class)->toArray())->except('api_key')->all());
    }

    public function form(Schema $schema): Schema
    {
        $settings = app(SmsSettings::class);
        $gateways = collect(config('sms.gateways'))->map(fn (string $class) => $class::label())->all();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('গেটওয়ে')->schema([
                    Toggle::make('enabled')->label('SMS চালু')->helperText('বন্ধ থাকলে কোনো SMS যাবে না।')->columnSpanFull(),
                    Grid::make(3)->schema([
                        Select::make('gateway')->label('SMS কোম্পানি')->options($gateways)->required()->native(false)->live()
                            ->helperText('অ্যাকাউন্ট খুলে API key নিন। টেস্ট মোডে SMS না পাঠিয়ে লগে লেখা হয়।'),
                        TextInput::make('api_key')->label('API key / token')->password()->revealable()->autocomplete('off')->maxLength(300)
                            ->placeholder(filled($settings->api_key) ? '•••••••• সেভ করা আছে — বদলাতে নতুনটা দিন' : null)
                            ->hidden(fn (Get $get) => $get('gateway') === 'log'),
                        TextInput::make('sender_id')->label('Sender ID / SID')->maxLength(30)
                            ->helperText('কোম্পানি যে Sender ID বা মাস্কিং নাম দিয়েছে')
                            ->hidden(fn (Get $get) => $get('gateway') === 'log'),
                    ]),
                    TextInput::make('admin_phones')->label('নতুন অর্ডারের অ্যালার্ট যাবে যে নম্বরে')->maxLength(200)
                        ->placeholder('01711000000, 01811000000')->helperText('কমা দিয়ে একাধিক নম্বর'),
                    Actions::make([
                        Action::make('test')->label('টেস্ট SMS পাঠান')->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                            ->schema([TextInput::make('phone')->label('মোবাইল নম্বর')->tel()->required()])
                            ->action(fn (array $data) => $this->sendTest($data['phone'])),
                    ]),
                ]),
                Section::make('মেসেজ টেমপ্লেট')
                    ->description('যা বসাতে পারবেন: '.implode('  ', SmsSettings::PLACEHOLDERS).'। বাংলা SMS-এ প্রতি ৭০ অক্ষরে ১টা SMS ধরা হয়।')
                    ->schema(collect(SmsSettings::EVENTS)->map(fn (string $label, string $key) => Grid::make(['default' => 1, 'md' => 4])->schema([
                        Toggle::make("templates.{$key}.enabled")->label($label)->inline(false),
                        Textarea::make("templates.{$key}.body")->hiddenLabel()->rows(2)->maxLength(480)->columnSpan(['md' => 3])
                            ->required(fn (Get $get) => (bool) $get("templates.{$key}.enabled")),
                    ]))->values()->all()),
                Section::make('কার্ট রিকভারি SMS')
                    ->description('চেকআউটে নম্বর দিয়েও যারা অর্ডার করেননি, তাদের একবার SMS যায় — লিংকে চাপলে একই কার্ট আর ঠিকানা ফিরে আসে। রাত ৯টা থেকে সকাল ৯টা পর্যন্ত যায় না; একই নম্বরে সপ্তাহে একবারের বেশি না। চেকআউট সেটিংসে "ইনকমপ্লিট অর্ডার সংরক্ষণ" চালু থাকতে হবে। যা বসাতে পারবেন: '.implode('  ', SmsSettings::RECOVERY_PLACEHOLDERS))
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('recovery_enabled')->label('চালু')->inline(false),
                            Select::make('recovery_delay_minutes')->label('কতক্ষণ পরে যাবে')->native(false)->required()
                                ->options([30 => '৩০ মিনিট', 60 => '১ ঘণ্টা', 120 => '২ ঘণ্টা', 240 => '৪ ঘণ্টা', 720 => '১২ ঘণ্টা']),
                            TextInput::make('recovery_coupon')->label('কুপন কোড (ঐচ্ছিক)')->maxLength(40)
                                ->helperText('মার্কেটিং → কুপনে আগে কুপনটা বানিয়ে রাখুন'),
                        ]),
                        Textarea::make('recovery_template')->label('মেসেজ')->rows(2)->maxLength(480)
                            ->required(fn (Get $get) => (bool) $get('recovery_enabled'))
                            ->rule(fn () => fn (string $attribute, $value, \Closure $fail) => blank($value) || str_contains((string) $value, '{link}') ? null : $fail('মেসেজে {link} থাকতে হবে।')),
                    ]),
                Section::make('স্টকে এলে জানান')
                    ->description('পণ্য স্টকে না থাকলে কাস্টমার নম্বর রেখে যেতে পারেন; স্টক আবার এলে এই SMS যায়। যা বসাতে পারবেন: {product}  {link}  {shop}')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 4])->schema([
                            Toggle::make('stock_alert_enabled')->label('চালু')->inline(false),
                            Textarea::make('stock_alert_template')->hiddenLabel()->rows(2)->maxLength(480)->columnSpan(['md' => 3])
                                ->required(fn (Get $get) => (bool) $get('stock_alert_enabled')),
                        ]),
                    ]),
                Section::make('লগইন কোড (OTP)')
                    ->description('কাস্টমার অ্যাকাউন্টে ঢোকার কোড। উপরের SMS চালু/বন্ধ সুইচ যা-ই থাকুক, এটা সবসময় যায়। যা বসাতে পারবেন: {code}  {shop}')
                    ->schema([
                        Textarea::make('otp_template')->hiddenLabel()->rows(2)->maxLength(300)->required()
                            ->rule(fn () => fn (string $attribute, $value, \Closure $fail) => str_contains((string) $value, '{code}') ? null : $fail('মেসেজে {code} থাকতে হবে।')),
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

        $settings = app(SmsSettings::class);
        $settings->fill($state)->save();
        $this->form->fill(collect($settings->toArray())->except('api_key')->all());

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
            // Sent directly (not queued) so the result shows up right here.
            $sender = app(SmsSender::class);
            $sender->gateway()->send($phone, 'টেস্ট SMS — '.config('app.name').' থেকে। কাজ করছে!');
            Notification::make()->success()->title('পাঠানো হয়েছে')->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title('পাঠানো যায়নি')->body($e->getMessage())->send();
        }
    }
}

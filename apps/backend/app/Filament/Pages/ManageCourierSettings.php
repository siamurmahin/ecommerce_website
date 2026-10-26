<?php

namespace App\Filament\Pages;

use App\Domain\Courier\CourierManager;
use App\Settings\CourierSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
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
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/** Courier API credentials, webhook URLs, order auto-update and fraud-check options. */
class ManageCourierSettings extends Page
{
    use HasPageShield;

    /** Never sent to the browser; a blank field on save keeps the stored value. */
    private const SECRETS = ['webhook_token', 'steadfast_api_key', 'steadfast_secret_key', 'pathao_client_secret', 'pathao_password', 'redx_token', 'fraud_api_key'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'কুরিয়ার ও ফ্রড চেক';

    protected static ?string $title = 'কুরিয়ার ও ফ্রড চেক';

    protected static ?string $slug = 'settings/courier';

    protected static ?int $navigationSort = 9;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->fillForm();
    }

    public function form(Schema $schema): Schema
    {
        $settings = app(CourierSettings::class);
        $secret = fn (string $name, string $label) => TextInput::make($name)->label($label)->password()->revealable()
            ->autocomplete('off')->maxLength(500)
            ->placeholder(filled($settings->{$name}) ? '•••••••• সেভ করা আছে — বদলাতে নতুনটা দিন' : null);
        $test = fn (string $courier) => Actions::make([
            Action::make("test_{$courier}")->label('কানেকশন টেস্ট (আগে সেভ করুন)')->icon(Heroicon::OutlinedSignal)->color('gray')->size('sm')
                ->action(fn () => $this->testCourier($courier)),
        ]);
        $webhook = fn (string $courier, string $how) => Text::make(fn () => 'Webhook URL: '.$this->webhookUrl($courier)." — {$how}")->color('gray');

        return $schema
            ->statePath('data')
            ->components([
                Section::make('সাধারণ')->columns(2)->schema([
                    Toggle::make('enabled')->label('কুরিয়ার API চালু')->inline(false)->columnSpanFull()
                        ->helperText('বন্ধ থাকলে অর্ডার পেজে "কুরিয়ারে বুক" দেখাবে না, স্ট্যাটাস পোলিং আর webhook-ও বন্ধ থাকবে।'),
                    Select::make('default_courier')->label('ডিফল্ট কুরিয়ার')->options(app(CourierManager::class)->labels())->native(false)
                        ->helperText('বুকিং ডায়ালগে আগে থেকে বাছা থাকবে'),
                    Toggle::make('auto_update_order')->label('কুরিয়ারের স্ট্যাটাস থেকে অর্ডার স্ট্যাটাস অটো বদলাও')->inline(false)
                        ->helperText('ডেলিভারড হলে "ডেলিভারড", রিটার্ন হলে "রিটার্নড" (স্টক ফেরত আসে)'),
                    TextInput::make('webhook_token')->label('Webhook সিক্রেট টোকেন')->password()->revealable()->autocomplete('off')->minLength(20)->maxLength(100)
                        ->placeholder('•••••••• সেভ করা আছে — বদলাতে নতুনটা দিন')
                        ->hintAction(Action::make('revealToken')->label('দেখুন')->icon(Heroicon::OutlinedEye)
                            ->modalHeading('Webhook সিক্রেট টোকেন')->modalSubmitAction(false)
                            ->modalDescription(fn () => app(CourierSettings::class)->webhook_token)
                            ->modalContent(fn () => new HtmlString('<p class="text-sm break-all">'.e('RedX callback URL: '.url('/api/v1/webhooks/courier/redx').'?token='.urlencode(app(CourierSettings::class)->webhook_token)).'</p>')))
                        ->helperText('কুরিয়ারের প্যানেলে webhook সেট করার সময় এটা লাগবে')->columnSpanFull(),
                ]),
                Section::make('Steadfast')->description('portal.packzy.com → API সেটিংস থেকে API Key আর Secret Key নিন।')->collapsible()->schema([
                    Grid::make(2)->schema([
                        $secret('steadfast_api_key', 'API Key'),
                        $secret('steadfast_secret_key', 'Secret Key'),
                    ]),
                    $webhook('steadfast', 'Auth Token হিসেবে উপরের সিক্রেট টোকেন দিন (Bearer)'),
                    $test('steadfast'),
                ]),
                Section::make('Pathao')->description('merchant.pathao.com → Developers API থেকে Client ID/Secret নিন।')->collapsible()->schema([
                    Grid::make(3)->schema([
                        TextInput::make('pathao_client_id')->label('Client ID')->maxLength(200),
                        $secret('pathao_client_secret', 'Client Secret'),
                        TextInput::make('pathao_store_id')->label('Store ID')->numeric()->integer(),
                        TextInput::make('pathao_username')->label('লগইন ইমেইল')->email()->maxLength(200),
                        $secret('pathao_password', 'লগইন পাসওয়ার্ড'),
                        Toggle::make('pathao_sandbox')->label('স্যান্ডবক্স (টেস্ট) মোড')->inline(false),
                    ]),
                    $webhook('pathao', 'Webhook Secret হিসেবে উপরের সিক্রেট টোকেন দিন'),
                    $test('pathao'),
                ]),
                Section::make('RedX')->description('redx.com.bd মার্চেন্ট প্যানেল → Developer API থেকে টোকেন নিন।')->collapsible()->schema([
                    Grid::make(3)->schema([
                        $secret('redx_token', 'API Access Token'),
                        TextInput::make('redx_pickup_store_id')->label('Pickup Store ID (ঐচ্ছিক)')->numeric()->integer(),
                        Toggle::make('redx_sandbox')->label('স্যান্ডবক্স (টেস্ট) মোড')->inline(false),
                    ]),
                    $webhook('redx', 'এই URL-টাই callback হিসেবে দিন (টোকেন URL-এ আছে)'),
                    $test('redx'),
                ]),
                Section::make('ফ্রড চেক')->description('ফোন নম্বরের আগের পার্সেল ডেলিভারি/রিটার্ন দেখে ভুয়া অর্ডার ধরা।')->columns(2)->schema([
                    Select::make('fraud_driver')->label('কোথা থেকে চেক হবে')->native(false)->required()->live()
                        ->options(collect(config('courier.fraud_checkers'))->map(fn (string $class) => $class::label())->all()),
                    $secret('fraud_api_key', 'API key')->visible(fn (Get $get) => $get('fraud_driver') !== 'internal'),
                    Toggle::make('fraud_auto_check')->label('প্রতিটা নতুন অর্ডার অটো চেক')->inline(false),
                    TextInput::make('fraud_risky_below')->label('এর কম সফল হলে "ঝুঁকিপূর্ণ" (%)')->numeric()->integer()->minValue(1)->maxValue(100)->required(),
                    TextInput::make('fraud_cache_hours')->label('একই নম্বর আবার চেক হবে (ঘণ্টা পর)')->numeric()->integer()->minValue(1)->maxValue(720)->required(),
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
        foreach (self::SECRETS as $key) {
            if (blank($state[$key] ?? null)) {
                unset($state[$key]);
            }
        }

        app(CourierSettings::class)->fill($state)->save();
        $this->fillForm();

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }

    public function testCourier(string $courier): void
    {
        try {
            $result = app(CourierManager::class)->driver($courier)->test();
            Notification::make()->success()->title(config("courier.couriers.{$courier}")::label().' কাজ করছে')->body($result)->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title('কানেক্ট হয়নি')->body(Str::limit($e->getMessage(), 300))->send();
        }
    }

    private function webhookUrl(string $courier): string
    {
        $url = url("/api/v1/webhooks/courier/{$courier}");

        // RedX can't send custom headers, so its token rides on the URL; others get it as a header.
        return $courier === 'redx' ? $url.'?token=••••' : $url;
    }

    private function fillForm(): void
    {
        $this->form->fill(collect(app(CourierSettings::class)->toArray())->except(self::SECRETS)->all());
    }
}

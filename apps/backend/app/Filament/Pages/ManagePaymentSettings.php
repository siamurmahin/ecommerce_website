<?php

namespace App\Filament\Pages;

use App\Domain\Order\Enums\PaymentMethod;
use App\Settings\PaymentSettings;
use App\Support\Phone;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
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
use Illuminate\Validation\ValidationException;
use UnitEnum;

/** Cash on delivery and manual bKash/Nagad/Rocket numbers shown at checkout. */
class ManagePaymentSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'পেমেন্ট';

    protected static ?string $title = 'পেমেন্ট সেটিংস';

    protected static ?string $slug = 'settings/payment';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(PaymentSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('ক্যাশ অন ডেলিভারি')->schema([
                    Toggle::make('cod_enabled')->label('ক্যাশ অন ডেলিভারি চালু'),
                    Radio::make('cod_advance')->label('অগ্রিম')->required()
                        ->options([
                            'none' => 'অগ্রিম লাগবে না — পুরো টাকা ডেলিভারির সময়',
                            'delivery' => 'ডেলিভারি চার্জ আগে বিকাশ/নগদে নিতে হবে, বাকিটা ডেলিভারির সময়',
                        ])
                        ->helperText('অগ্রিম চালু করলে নিচে অন্তত একটা মোবাইল ব্যাংকিং নম্বর চালু রাখুন।'),
                ]),
                Section::make('মোবাইল ব্যাংকিং (ম্যানুয়াল)')
                    ->description('কাস্টমার আপনার নম্বরে টাকা পাঠিয়ে চেকআউটে ট্রানজেকশন আইডি (TrxID) দেবে। অর্ডার পেজের "পেমেন্ট যাচাই" থেকে মিলিয়ে কনফার্ম করবেন। অটোমেটিক গেটওয়ে (SSLCommerz, bKash PGW) পরে যোগ হবে।')
                    ->schema(collect(PaymentMethod::wallets())->map(fn (PaymentMethod $m) => Section::make($m->getLabel())->compact()->schema([
                        Toggle::make("wallets.{$m->value}.enabled")->label('চালু')->live(),
                        Grid::make(2)->schema([
                            TextInput::make("wallets.{$m->value}.number")->label('নম্বর')->tel()->placeholder('01XXXXXXXXX')
                                ->required(fn (Get $get) => (bool) $get("wallets.{$m->value}.enabled"))
                                ->dehydrateStateUsing(fn ($state) => filled($state) ? Phone::normalize($state) : null)
                                ->rule(fn () => fn (string $attribute, $value, \Closure $fail) => blank($value) || preg_match(Phone::PATTERN, Phone::normalize($value)) ? null : $fail('সঠিক নম্বর দিন।')),
                            Select::make("wallets.{$m->value}.type")->label('অ্যাকাউন্টের ধরন')->options(PaymentSettings::ACCOUNT_TYPES)->required()->native(false),
                        ]),
                        Textarea::make("wallets.{$m->value}.instructions")->label('কাস্টমারের জন্য নির্দেশনা (ঐচ্ছিক)')->rows(2)->maxLength(400)
                            ->placeholder('রেফারেন্সে আপনার মোবাইল নম্বর লিখুন।'),
                    ]))->all()),
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
        $settings = app(PaymentSettings::class);
        $settings->fill($state);

        if (! $settings->cod_enabled && $settings->activeWallets() === []) {
            throw ValidationException::withMessages(['data.cod_enabled' => 'অন্তত একটা পেমেন্ট মাধ্যম চালু রাখুন।']);
        }
        if ($settings->cod_advance === 'delivery' && $settings->activeWallets() === []) {
            throw ValidationException::withMessages(['data.cod_advance' => 'অগ্রিম নিতে একটা মোবাইল ব্যাংকিং নম্বর চালু করুন।']);
        }

        $settings->save();
        $this->form->fill($settings->toArray());

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }
}

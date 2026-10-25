<?php

namespace App\Filament\Pages;

use App\Domain\Customer\Models\Customer;
use App\Domain\Reward\Models\PointTransaction;
use App\Settings\RewardSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
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
use UnitEnum;

/** Loyalty points and referral codes. Money fields are entered in taka and stored as poisha. */
class Rewards extends Page
{
    use HasPageShield;

    private const MONEY = ['point_value', 'referee_discount', 'referee_min_subtotal'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'মার্কেটিং';

    protected static ?string $navigationLabel = 'রিওয়ার্ড ও রেফারেল';

    protected static ?string $title = 'রিওয়ার্ড ও রেফারেল';

    protected static ?string $slug = 'rewards';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $values = app(RewardSettings::class)->toArray();
        foreach (self::MONEY as $key) {
            $values[$key] = $values[$key] / 100;
        }
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $outstanding = (int) Customer::sum('points_balance');
        $earned30 = (int) PointTransaction::whereIn('type', [PointTransaction::EARN, PointTransaction::REFERRAL])->where('created_at', '>=', now()->subDays(30))->sum('points');
        $referred = Customer::whereNotNull('referred_by_id')->count();

        return $schema
            ->statePath('data')
            ->components([
                Text::make("কাস্টমারদের কাছে মোট পয়েন্ট: {$outstanding} · গত ৩০ দিনে দেওয়া: {$earned30} · রেফারেলে আসা কাস্টমার: {$referred}"),
                Section::make('পয়েন্ট')
                    ->description('অর্ডার ডেলিভারড হলে কাস্টমার পয়েন্ট পান; পরের অর্ডারে চেকআউটে পয়েন্ট দিয়ে ছাড় নিতে পারেন (লগইন করে, নিজের নম্বরে)। অর্ডার বাতিল/রিটার্ন হলে পয়েন্ট নিজে থেকেই ফেরত বা বাদ হয়।')
                    ->schema([
                        Toggle::make('points_enabled')->label('পয়েন্ট চালু')->live(),
                        Grid::make(2)->visible(fn (Get $get) => (bool) $get('points_enabled'))->schema([
                            TextInput::make('earn_per_100')->label('প্রতি ৳১০০ কেনাকাটায় পয়েন্ট')->numeric()->integer()->minValue(0)->maxValue(1000)->required()
                                ->helperText('ডেলিভারি চার্জ বাদে, ছাড়ের পরের দামে'),
                            TextInput::make('point_value')->label('১ পয়েন্টের মূল্য (৳)')->numeric()->minValue(0.01)->maxValue(1000)->step(0.01)->required(),
                            TextInput::make('min_redeem')->label('একবারে কমপক্ষে কত পয়েন্ট ব্যবহার')->numeric()->integer()->minValue(1)->required(),
                            TextInput::make('max_redeem_percent')->label('পণ্যের দামের সর্বোচ্চ কত % পয়েন্টে')->numeric()->integer()->minValue(1)->maxValue(100)->suffix('%')->required(),
                        ]),
                    ]),
                Section::make('রেফারেল')
                    ->description('প্রত্যেক কাস্টমার অ্যাকাউন্টে নিজের কোড আর লিংক পান। বন্ধু লিংকে এলে বা চেকআউটে কুপনের ঘরে কোডটা দিলে প্রথম অর্ডারে ছাড় পান; সেই অর্ডার ডেলিভারড হলে যিনি রেফার করেছেন তিনি পয়েন্ট পান।')
                    ->schema([
                        Toggle::make('referral_enabled')->label('রেফারেল চালু')->live(),
                        Grid::make(3)->visible(fn (Get $get) => (bool) $get('referral_enabled'))->schema([
                            TextInput::make('referee_discount')->label('বন্ধুর প্রথম অর্ডারে ছাড় (৳)')->numeric()->minValue(0)->required(),
                            TextInput::make('referee_min_subtotal')->label('ছাড়ের জন্য কমপক্ষে অর্ডার (৳)')->numeric()->minValue(0)->required(),
                            TextInput::make('referrer_points')->label('রেফারকারী পাবেন (পয়েন্ট)')->numeric()->integer()->minValue(0)->required()
                                ->helperText('পয়েন্ট বন্ধ থাকলেও জমা হয়, চালু করলে ব্যবহার করা যাবে'),
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
        $settings = app(RewardSettings::class);

        // Hidden sections aren't in the state; keep their stored values.
        foreach ($state as $key => $value) {
            $settings->{$key} = in_array($key, self::MONEY, true) ? (int) round((float) $value * 100) : $value;
        }
        $settings->save();

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }
}

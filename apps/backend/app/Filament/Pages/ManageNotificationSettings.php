<?php

namespace App\Filament\Pages;

use App\Domain\Order\Models\Order;
use App\Mail\NewOrderMail;
use App\Settings\NotificationSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/** Where new website orders are announced: email to the shop and the admin bell. */
class ManageNotificationSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'নতুন অর্ডারের নোটিফিকেশন';

    protected static ?string $title = 'নতুন অর্ডারের নোটিফিকেশন';

    protected static ?string $slug = 'settings/notifications';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(NotificationSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('ইমেইল')
                    ->description('ওয়েবসাইটে নতুন অর্ডার এলেই এই ঠিকানাগুলোতে ইমেইল যাবে — কাস্টমারের নাম, ফোন, ঠিকানা, পণ্য আর মোট টাকাসহ। ইমেইল পাঠাতে সার্ভারে মেইল (SMTP) সেট করা থাকতে হবে; নিচের "টেস্ট ইমেইল" দিয়ে যাচাই করুন।')
                    ->schema([
                        Toggle::make('new_order_email')->label('নতুন অর্ডারে ইমেইল পাঠাও'),
                        Textarea::make('emails')->label('কোন ইমেইলে যাবে')->rows(2)->maxLength(1000)
                            ->placeholder('owner@gmail.com, staff@gmail.com')
                            ->helperText('একাধিক হলে কমা দিয়ে আলাদা করুন।')
                            ->rule(fn () => function (string $attribute, $value, $fail) {
                                $bad = collect(preg_split('/[\s,;]+/', (string) $value))->filter()->reject(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
                                if ($bad->isNotEmpty()) {
                                    $fail('ঠিক ইমেইল না: '.$bad->implode(', '));
                                }
                            }),
                    ]),
                Section::make('অ্যাডমিন প্যানেল')
                    ->schema([
                        Toggle::make('admin_bell')->label('অ্যাডমিনের বেল আইকনে নতুন অর্ডার দেখাও')
                            ->helperText('যেসব স্টাফ অর্ডার দেখতে পারেন, তাঁদের সবার বেলে আসবে (৩০ সেকেন্ড পরপর আপডেট হয়)।'),
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
                        Action::make('testEmail')->label('টেস্ট ইমেইল পাঠান (আগে সেভ করুন)')->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                            ->action(fn () => $this->sendTest()),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        $settings = app(NotificationSettings::class);
        $settings->fill($this->form->getState())->save();

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }

    /** Sends right away (not queued) so a wrong SMTP setting shows up here. */
    public function sendTest(): void
    {
        $emails = app(NotificationSettings::class)->emailList();
        if (! $emails) {
            Notification::make()->warning()->title('আগে ইমেইল ঠিকানা দিয়ে সেভ করুন')->send();

            return;
        }

        $order = Order::query()->with('items')->latest('id')->first();

        try {
            $order
                ? Mail::to($emails)->sendNow(new NewOrderMail($order))
                : Mail::raw('Looks Jhorna: নতুন অর্ডারের ইমেইল ঠিকমতো আসছে।', fn ($m) => $m->to($emails)->subject('টেস্ট ইমেইল'));
        } catch (Throwable $e) {
            Notification::make()->danger()->title('ইমেইল যায়নি')->body(Str::limit($e->getMessage(), 300))->persistent()->send();

            return;
        }

        $note = config('mail.default') === 'log' ? ' (এখন মেইল "log" মোডে আছে — আসল ইমেইল যাবে না, সার্ভারে SMTP সেট করুন)' : '';
        Notification::make()->success()->title('টেস্ট ইমেইল পাঠানো হয়েছে'.$note)
            ->body(($order ? "শেষ অর্ডার {$order->number} দিয়ে" : 'সাধারণ টেস্ট').' → '.implode(', ', $emails))->send();
    }
}

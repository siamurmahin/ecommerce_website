<?php

namespace App\Filament\Pages;

use App\Settings\ReviewRequestSettings;
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

/** The email that asks customers for a review a few days after delivery. */
class ManageReviewRequestSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'রিভিউ অনুরোধ ইমেইল';

    protected static ?string $title = 'রিভিউ অনুরোধ ইমেইল';

    protected static ?string $slug = 'settings/review-request';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(ReviewRequestSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->description('ডেলিভারির কয়েক দিন পর কাস্টমারকে একবার ইমেইল যাবে, "রিভিউ লিখুন" বাটনে চাপলে অ্যাকাউন্টের রিভিউ অংশ খুলবে। শুধু যেসব অর্ডারে ইমেইল দেওয়া আছে সেগুলোতে যায়। ইমেইল পাঠানোর জন্য সার্ভারে মেইল (SMTP) সেট করা থাকতে হবে। যা বসাতে পারবেন: '.implode('  ', ReviewRequestSettings::PLACEHOLDERS))
                    ->schema([
                        Toggle::make('enabled')->label('রিভিউ অনুরোধ ইমেইল চালু'),
                        Select::make('delay_days')->label('ডেলিভারির কত দিন পরে')->native(false)->selectablePlaceholder(false)
                            ->options([1 => '১ দিন', 2 => '২ দিন', 3 => '৩ দিন', 5 => '৫ দিন', 7 => '৭ দিন', 14 => '১৪ দিন']),
                        TextInput::make('subject')->label('বিষয় (Subject)')->required()->maxLength(150),
                        Textarea::make('body')->label('লেখা')->rows(7)->required()->maxLength(2000),
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
        $settings = app(ReviewRequestSettings::class);
        $settings->fill($this->form->getState())->save();

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }
}

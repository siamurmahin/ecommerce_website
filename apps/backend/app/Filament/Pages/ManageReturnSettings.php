<?php

namespace App\Filament\Pages;

use App\Settings\ReturnSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
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

/** Whether customers can ask for a return/exchange from their order page, for how long, and the policy they see. */
class ManageReturnSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'রিটার্ন ও এক্সচেঞ্জ';

    protected static ?string $title = 'রিটার্ন ও এক্সচেঞ্জ সেটিংস';

    protected static ?string $slug = 'settings/returns';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(ReturnSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()->schema([
                    Toggle::make('enabled')->label('কাস্টমার অর্ডার পেজ থেকে রিটার্ন/এক্সচেঞ্জ চাইতে পারবেন')
                        ->helperText('বন্ধ থাকলেও স্টাফ অর্ডারের পাতা থেকে রিটার্ন খুলতে পারবেন।'),
                    TextInput::make('window_days')->label('ডেলিভারির কত দিনের মধ্যে')->numeric()->integer()->minValue(1)->maxValue(90)->suffix('দিন')->required(),
                    Textarea::make('policy')->label('রিটার্ন নীতি (বাংলা)')->rows(3)->maxLength(2000)->required(),
                    Textarea::make('policy_en')->label('Return policy (English)')->rows(3)->maxLength(2000)->required(),
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
        $settings = app(ReturnSettings::class);
        $settings->fill($this->form->getState());
        $settings->save();

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }
}

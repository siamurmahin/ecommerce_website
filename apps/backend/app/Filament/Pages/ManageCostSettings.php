<?php

namespace App\Filament\Pages;

use App\Filament\Support\Fields;
use App\Settings\CostSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
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

/** Costs shared by every product, and who pays for free delivery; only the profit report uses them. */
class ManageCostSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'খরচ';

    protected static ?string $title = 'খরচ সেটিংস';

    protected static ?string $slug = 'settings/costs';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(CostSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('সব পণ্যের সাধারণ খরচ (প্রতি পিস)')
                    ->description('যে খরচ প্রায় সব পণ্যে একই — যেমন প্যাকেজিং বা মাল আনার ট্রান্সপোর্ট ভাগ করে প্রতি পিসে কত পড়ে। প্রতিটা পণ্যের খরচে যোগ হবে; কোনো পণ্যে না চাইলে সেই পণ্যের "খরচের হিসাব" থেকে বন্ধ করা যায়। এককালীন বড় খরচ (একবারের ট্রান্সপোর্ট বিল, বিজ্ঞাপন) রিপোর্ট → খরচের তালিকায় দিন।')
                    ->schema([
                        Repeater::make('shared')->hiddenLabel()
                            ->schema([
                                TextInput::make('label')->label('খরচ')->placeholder('যেমন: প্যাকেজিং, ট্রান্সপোর্ট')->required()->maxLength(60),
                                Fields::money('amount', 'প্রতি পিসে টাকা')->required(),
                            ])
                            ->columns(2)->defaultItems(0)->maxItems(10)->reorderable(false)
                            ->addActionLabel('সাধারণ খরচ যোগ করুন'),
                    ]),
                Section::make('ফ্রি ডেলিভারি')
                    ->schema([
                        Toggle::make('owner_pays_free_delivery')->label('ফ্রি ডেলিভারির চার্জ দোকান নিজে দেয়')
                            ->helperText('নির্দিষ্ট টাকার বেশি অর্ডার বা কুপনে ডেলিভারি ফ্রি হলে জোনের চার্জটা দোকানের খরচ ধরা হবে। "ফ্রি ডেলিভারি" দেওয়া পণ্যের জন্য সেই পণ্যের নিজস্ব সেটিং চলে।'),
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
        $settings = app(CostSettings::class);
        $settings->fill($this->form->getState());
        $settings->shared = array_values($settings->shared);
        $settings->save();
        $this->form->fill($settings->toArray());

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }
}

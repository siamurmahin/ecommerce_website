<?php

namespace App\Filament\Pages;

use App\Settings\SearchSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Synonyms and quick picks for storefront search. */
class ManageSearchSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'ক্যাটালগ';

    protected static ?string $navigationLabel = 'সার্চ';

    protected static ?string $title = 'সার্চ সেটিংস';

    protected static ?string $slug = 'search';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(SearchSettings::class);
        $this->form->fill([
            'synonyms' => implode("\n", $settings->synonyms),
            'popular' => $settings->popular,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('সমার্থক শব্দ')
                    ->description('প্রতি লাইনে একই জিনিসের সব নাম, কমা দিয়ে আলাদা — বাংলা, ইংরেজি, প্রচলিত ভুল বানান। কেউ এর যেকোনো একটা লিখলে সবগুলো দিয়ে খোঁজা হবে। এর বাইরেও ছোটখাটো বানান ভুল সার্চ নিজে থেকে ঠিক করে নেয়।')
                    ->schema([
                        Textarea::make('synonyms')->hiddenLabel()->rows(14)->maxLength(10000)->placeholder("আতর, attar, ator, itr\nপাঞ্জাবি, panjabi, punjabi"),
                    ]),
                Section::make('জনপ্রিয় সার্চ')
                    ->description('সার্চ বক্সে ক্লিক করলে, কিছু লেখার আগে, এগুলো দেখাবে।')
                    ->schema([
                        TagsInput::make('popular')->hiddenLabel()->placeholder('লিখে Enter চাপুন')->nestedRecursiveRules(['max:40']),
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
        $settings = app(SearchSettings::class);
        $settings->synonyms = collect(preg_split('/\R/u', (string) ($state['synonyms'] ?? '')))->map(fn ($w) => trim((string) $w))->filter()->values()->all();
        $settings->popular = array_values(array_slice(array_filter($state['popular'] ?? []), 0, 12));
        $settings->save();

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }
}

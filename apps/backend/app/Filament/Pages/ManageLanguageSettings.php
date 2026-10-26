<?php

namespace App\Filament\Pages;

use App\Settings\LanguageSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Storefront languages: both (with the one first-time visitors see), English only or Bangla only. */
class ManageLanguageSettings extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'ভাষা';

    protected static ?string $title = 'ওয়েবসাইটের ভাষা';

    protected static ?string $slug = 'settings/language';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(LanguageSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()->schema([
                    Radio::make('mode')->label('ওয়েবসাইটে কোন ভাষা থাকবে')->options(LanguageSettings::MODES)->required()->live()
                        ->helperText('একটা ভাষা বেছে নিলে অন্য ভাষার লিংকে এলে এই ভাষায় পাঠিয়ে দেওয়া হবে, আর ভাষা বদলের বাটন লুকানো থাকবে। ল্যান্ডিং পেজ (/l/…) যে ভাষার লিংক সেই ভাষাতেই খোলে।'),
                    Radio::make('default')->label('প্রথমবার এলে কোন ভাষা দেখবেন')->options(['en' => 'ইংরেজি', 'bn' => 'বাংলা'])->required()->inline()
                        ->visible(fn (Get $get) => $get('mode') === 'both')
                        ->helperText('কাস্টমার নিজে ভাষা বদলালে পরের বার সেটাই মনে রাখা হবে। পণ্যের ইংরেজি নাম/বিবরণ না থাকলে বাংলাটাই দেখাবে।'),
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
        $settings = app(LanguageSettings::class);
        $settings->fill($this->form->getState());
        $settings->save();

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }
}

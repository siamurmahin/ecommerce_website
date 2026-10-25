<?php

namespace App\Filament\Pages;

use App\Domain\Catalog\Feeds\ProductFeed;
use App\Domain\Catalog\Models\Category;
use App\Http\Controllers\FeedController;
use App\Settings\FeedSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/** Catalog feed URLs for Meta Commerce Manager and Google Merchant Center, and what goes in them. */
class ProductFeeds extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    protected static string|UnitEnum|null $navigationGroup = 'মার্কেটিং';

    protected static ?string $navigationLabel = 'প্রোডাক্ট ফিড';

    protected static ?string $title = 'প্রোডাক্ট ফিড';

    protected static ?string $slug = 'product-feeds';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(FeedSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('ফিড লিংক')
                    ->description('Commerce Manager → ক্যাটালগ → Data sources → Data feed, আর Merchant Center → Products → Feeds-এ "Scheduled fetch" হিসেবে লিংক দিন (দিনে একবার বা প্রতি ঘণ্টায়)। দাম, স্টক আর ফ্ল্যাশ সেলের দাম নিজে থেকে আপডেট হয়।')
                    ->schema([
                        Toggle::make('enabled')->label('ফিড চালু')->helperText('বন্ধ থাকলে লিংকগুলো কাজ করবে না।'),
                        ...collect(ProductFeed::CHANNELS)->map(fn (string $label, string $channel) => Text::make(fn () => "{$label}: ".route('feeds.show', $channel))->copyable()->copyableState(fn () => route('feeds.show', $channel))->color('gray'))->values()->all(),
                        Text::make(fn () => 'এখন ফিডে আছে: '.number_format(count(app(ProductFeed::class)->items())).'টি আইটেম (প্রতিটা ভ্যারিয়েন্ট আলাদা আইটেম)।'),
                    ]),
                Section::make('কী কী যাবে')->schema([
                    Grid::make(2)->schema([
                        Select::make('category_ids')->label('শুধু এই ক্যাটাগরিগুলো')->multiple()->searchable()->preload()
                            ->options(fn () => Category::orderBy('name')->pluck('name', 'id'))
                            ->helperText('খালি রাখলে সব পণ্য। সাব-ক্যাটাগরিও ধরা হবে।'),
                        Radio::make('locale')->label('ভাষা')->options(['bn' => 'বাংলা', 'en' => 'English'])->required()->inline(),
                    ]),
                    Toggle::make('include_out_of_stock')->label('স্টক শেষ পণ্যও রাখুন ("out of stock" হিসেবে)')
                        ->helperText('রাখলে অ্যাড নিজে থেকে থামবে আর স্টক এলে আবার চলবে। বন্ধ করলে ফিড থেকে বাদ যাবে।'),
                    Grid::make(2)->schema([
                        TextInput::make('brand_fallback')->label('ব্র্যান্ড না থাকলে')->maxLength(70)
                            ->placeholder('খালি রাখলে দোকানের নাম')->helperText('Google-এ প্রতিটা পণ্যের ব্র্যান্ড লাগে।'),
                        TextInput::make('google_category')->label('Google প্রোডাক্ট ক্যাটাগরি (ঐচ্ছিক)')->maxLength(250)
                            ->placeholder('যেমন 5181 বা Apparel & Accessories > Clothing'),
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
                        Action::make('preview')->label('ফিড দেখুন')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                            ->url(fn () => route('feeds.show', 'facebook'))->openUrlInNewTab()
                            ->visible(fn () => app(FeedSettings::class)->enabled),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        $settings = app(FeedSettings::class);
        $state = $this->form->getState();
        $state['category_ids'] = array_map('intval', $state['category_ids'] ?? []);
        $settings->fill($state)->save();

        foreach (array_keys(ProductFeed::CHANNELS) as $channel) {
            Cache::forget(FeedController::CACHE_KEY.$channel);
        }

        Notification::make()->success()->title('সেভ হয়েছে')->send();
    }
}

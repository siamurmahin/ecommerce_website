<?php

namespace App\Filament\Pages;

use App\Domain\Tracking\Drivers\MetaConversionsApi;
use App\Settings\TrackingSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Client\RequestException;
use Throwable;
use UnitEnum;

/**
 * GTM, GA4, Meta Pixel + Conversions API, TikTok Pixel + Events API, Google Ads, Clarity, Search Console,
 * domain verification, site-wide and page-scoped custom scripts.
 * Entering an ID is enough: the storefront loads the tag and fires the standard ecommerce events.
 */
class ManageTrackingSettings extends Page
{
    use HasPageShield;

    /** Never sent to the browser: left blank in the form, and a blank save keeps the stored value. */
    private const SECRETS = ['ga4_api_secret', 'meta_capi_token', 'tiktok_access_token'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'ট্র্যাকিং ও স্ক্রিপ্ট';

    protected static ?string $title = 'ট্র্যাকিং ও স্ক্রিপ্ট';

    protected static ?string $slug = 'settings/tracking';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(app(TrackingSettings::class)->toArray())->except(self::SECRETS)->all());
    }

    public function form(Schema $schema): Schema
    {
        $settings = app(TrackingSettings::class);
        $on = fn (bool $enabled) => $enabled ? 'চালু' : null;

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('channels')->persistTabInQueryString()->tabs([
                    Tab::make('Google Tag Manager')->badge($on(filled($settings->gtm_id)))->badgeColor('success')->schema([
                        TextInput::make('gtm_id')->label('Container ID')->placeholder('GTM-XXXXXXX')->maxLength(20)
                            ->regex('/^\s*GTM-[A-Z0-9]+\s*$/i')->validationMessages(['regex' => 'GTM-XXXXXXX ফরম্যাটে দিন'])
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? strtoupper(trim($state)) : null)
                            ->helperText('কোথায় পাবেন: tagmanager.google.com → আপনার container → উপরে ডান দিকে GTM- দিয়ে শুরু ID।'),
                        Text::make('প্রতিটা ইভেন্ট dataLayer-এ GA4 ecommerce ফরম্যাটে যায় (view_item, add_to_cart, begin_checkout, purchase) — সাথে event_id থাকে। GTM-এর ভেতরে GA4 ট্যাগ বসালে নিচের GA4 ট্যাবের ID খালি রাখুন, নইলে হিট দুবার গুনবে।'),
                    ]),
                    Tab::make('Google Analytics 4')->badge($on(filled($settings->ga4_measurement_id)))->badgeColor('success')->schema([
                        TextInput::make('ga4_measurement_id')->label('Measurement ID')->placeholder('G-XXXXXXXXXX')->maxLength(20)
                            ->regex('/^\s*G-[A-Z0-9]+\s*$/i')->validationMessages(['regex' => 'G-XXXXXXXXXX ফরম্যাটে দিন'])
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? strtoupper(trim($state)) : null)
                            ->helperText('কোথায় পাবেন: GA4 → Admin → Data streams → আপনার web stream → Measurement ID।'),
                        $this->secretInput('ga4_api_secret', 'Measurement Protocol API secret', filled($settings->ga4_api_secret))
                            ->helperText('ঐচ্ছিক — অ্যাড-ব্লকার থাকলেও purchase সার্ভার থেকে GA4-এ যাবে। কোথায় পাবেন: একই web stream → Measurement Protocol API secrets → Create।'),
                    ]),
                    Tab::make('Meta (Facebook)')->badge($on(filled($settings->meta_pixel_id)))->badgeColor('success')->schema([
                        TextInput::make('meta_pixel_id')->label('Pixel (Dataset) ID')->placeholder('123456789012345')->maxLength(20)
                            ->regex('/^\d{8,20}$/')->validationMessages(['regex' => 'শুধু সংখ্যা — Pixel ID দিন'])
                            ->helperText('কোথায় পাবেন: business.facebook.com → Events Manager → Data sources → আপনার pixel → Settings → Dataset ID।'),
                        $this->secretInput('meta_capi_token', 'Conversions API access token', filled($settings->meta_capi_token))
                            ->helperText('দিলে ViewContent, AddToCart, InitiateCheckout আর Purchase সার্ভার থেকেও যাবে (একই event_id, তাই Meta দুবার গুনবে না)। কোথায় পাবেন: Events Manager → pixel → Settings → Conversions API → Generate access token।'),
                        TextInput::make('meta_test_event_code')->label('Test event code')->placeholder('TEST12345')->maxLength(30)
                            ->helperText('শুধু টেস্টের সময় দিন, পরে খালি করুন। কোথায় পাবেন: Events Manager → Test events → Conversions API ট্যাবের কোড।'),
                        Actions::make([
                            Action::make('testMeta')
                                ->label('Meta CAPI কানেকশন টেস্ট')
                                ->icon(Heroicon::OutlinedSignal)
                                ->color('gray')
                                ->disabled(fn () => ! app(TrackingSettings::class)->metaServerEnabled())
                                ->tooltip('আগে Pixel ID আর token সেভ করুন')
                                ->action(fn () => $this->testMeta()),
                        ]),
                    ]),
                    Tab::make('TikTok')->badge($on(filled($settings->tiktok_pixel_id)))->badgeColor('success')->schema([
                        TextInput::make('tiktok_pixel_id')->label('Pixel ID')->placeholder('C1A2B3C4D5E6F7G8H9I0')->maxLength(40)
                            ->regex('/^\s*[A-Z0-9]{10,40}\s*$/i')->validationMessages(['regex' => 'TikTok Pixel ID দিন (অক্ষর ও সংখ্যা)'])
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? strtoupper(trim($state)) : null)
                            ->helperText('কোথায় পাবেন: ads.tiktok.com → Tools → Events → Web events → আপনার pixel → Pixel ID।'),
                        $this->secretInput('tiktok_access_token', 'Events API access token', filled($settings->tiktok_access_token))
                            ->helperText('দিলে ViewContent, AddToCart, InitiateCheckout আর CompletePayment সার্ভার থেকেও যাবে (একই event_id)। কোথায় পাবেন: pixel → Settings → Events API → Generate access token।'),
                        TextInput::make('tiktok_test_event_code')->label('Test event code')->placeholder('TEST12345')->maxLength(30)
                            ->helperText('শুধু টেস্টের সময় দিন, পরে খালি করুন। কোথায় পাবেন: pixel → Test events।'),
                    ]),
                    Tab::make('Google Ads')->badge($on(filled($settings->google_ads_id)))->badgeColor('success')->schema([
                        TextInput::make('google_ads_id')->label('Conversion ID')->placeholder('AW-123456789')->maxLength(20)
                            ->regex('/^\s*AW-\d+\s*$/i')->validationMessages(['regex' => 'AW-123456789 ফরম্যাটে দিন'])
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? strtoupper(trim($state)) : null)
                            ->helperText('কোথায় পাবেন: Google Ads → Goals → Conversions → আপনার Purchase action → Tag setup → Install the tag yourself → send_to-এর AW- অংশ।'),
                        TextInput::make('google_ads_purchase_label')->label('Purchase conversion label')->placeholder('AbCdEfGhIjk')->maxLength(60)
                            ->regex('/^[A-Za-z0-9_-]+$/')
                            ->helperText('একই জায়গায় send_to: \'AW-…/এই অংশ\'। অর্ডার হলে মূল্যসহ কনভার্সন যায়; রিমার্কেটিং-এর জন্য প্রতি পেজে Google tag লোড হয়।'),
                    ]),
                    Tab::make('অন্যান্য')->badge($on(filled($settings->clarity_id) || filled($settings->gsc_verification) || filled($settings->fb_domain_verification)))->badgeColor('success')->schema([
                        TextInput::make('clarity_id')->label('Microsoft Clarity project ID')->placeholder('abcd1234ef')->maxLength(20)
                            ->regex('/^[a-z0-9]+$/i')
                            ->helperText('হিটম্যাপ আর সেশন রেকর্ডিং। কোথায় পাবেন: clarity.microsoft.com → project → Settings → Overview → Project ID।'),
                        TextInput::make('gsc_verification')->label('Google Search Console verification')->maxLength(300)
                            ->placeholder('<meta name="google-site-verification" content="…"> অথবা শুধু content')
                            ->dehydrateStateUsing(fn (?string $state) => self::verificationCode($state))
                            ->helperText('কোথায় পাবেন: Search Console → Add property → URL prefix → HTML tag। পুরো meta ট্যাগ পেস্ট করলেও চলবে।'),
                        TextInput::make('fb_domain_verification')->label('Facebook domain verification')->maxLength(300)
                            ->placeholder('<meta name="facebook-domain-verification" content="…"> অথবা শুধু content')
                            ->dehydrateStateUsing(fn (?string $state) => self::verificationCode($state))
                            ->helperText('কোথায় পাবেন: Business settings → Brand safety → Domains → Add → Meta-tag verification। পেস্ট করে সেভ করুন, তারপর সেখানে Verify চাপুন।'),
                    ]),
                    Tab::make('কাস্টম স্ক্রিপ্ট')->badge($on(filled($settings->head_scripts) || filled($settings->body_start_scripts) || filled($settings->body_end_scripts)))->badgeColor('success')->schema([
                        Text::make('অন্য কোনো টুলের কোড (TikTok pixel, chat widget ইত্যাদি) এখানে পেস্ট করুন। <script> ট্যাগসহ দিন; প্রতিটা পেজে লোড হবে। ভুল কোড সাইট ভেঙে দিতে পারে — শুধু বিশ্বস্ত সোর্সের কোড দিন।'),
                        Textarea::make('head_scripts')->label('<head>-এর ভেতরে')->rows(5)->maxLength(20000)->extraInputAttributes(['class' => 'font-mono text-xs']),
                        Textarea::make('body_start_scripts')->label('<body> শুরুতে')->rows(4)->maxLength(20000)->extraInputAttributes(['class' => 'font-mono text-xs']),
                        Textarea::make('body_end_scripts')->label('</body>-এর আগে')->rows(4)->maxLength(20000)->extraInputAttributes(['class' => 'font-mono text-xs']),
                    ]),
                    Tab::make('পেজভিত্তিক স্ক্রিপ্ট')->badge(fn () => count($settings->page_scripts) ?: null)->schema([
                        Text::make('কিছু কোড শুধু নির্দিষ্ট পেজে দরকার (যেমন শুধু চেকআউটে কোনো কনভার্সন ট্যাগ, বা শুধু একটা ল্যান্ডিং পেজে আলাদা pixel)। পাথ প্রতি লাইনে একটা; * মানে যেকোনো কিছু। উদাহরণ: /checkout, /p/*, /l/eid-offer, /order/*'),
                        Repeater::make('page_scripts')->hiddenLabel()->addActionLabel('স্ক্রিপ্ট যোগ করুন')->collapsible()->cloneable()
                            ->itemLabel(fn (array $state) => ($state['name'] ?? null) ?: 'নতুন স্ক্রিপ্ট')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('name')->label('নাম (নিজের জন্য)')->required()->maxLength(80),
                                    Select::make('placement')->label('কোথায় বসবে')->options(['head' => '<head>-এ', 'body_end' => '</body>-এর আগে'])->default('body_end')->required()->native(false),
                                    Toggle::make('enabled')->label('চালু')->default(true)->inline(false),
                                ]),
                                Textarea::make('paths')->label('পেজের পাথ')->required()->rows(2)->maxLength(2000)->placeholder("/checkout\n/p/*")
                                    ->extraInputAttributes(['class' => 'font-mono text-xs']),
                                Textarea::make('code')->label('কোড')->required()->rows(5)->maxLength(20000)->extraInputAttributes(['class' => 'font-mono text-xs']),
                            ]),
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
        $settings = app(TrackingSettings::class);
        $state = $this->form->getState();

        foreach (self::SECRETS as $secret) {
            if (blank($state[$secret] ?? null)) {
                unset($state[$secret]);
            }
        }

        $settings->fill($state)->save();
        $this->form->fill(collect($settings->toArray())->except(self::SECRETS)->all());

        Notification::make()->success()->title('সেভ হয়েছে')->body('স্টোরফ্রন্টে কয়েক সেকেন্ডের মধ্যে চালু হবে।')->send();
    }

    public function testMeta(): void
    {
        try {
            $name = app(MetaConversionsApi::class)->testConnection();
            $hasCode = filled(app(TrackingSettings::class)->meta_test_event_code);

            Notification::make()->success()->title('কানেক্টেড'.($name ? ": {$name}" : ''))
                ->body($hasCode ? 'একটা টেস্ট PageView পাঠানো হয়েছে — Events Manager → Test events-এ দেখুন।' : 'Pixel ID আর token ঠিক আছে।')
                ->send();
        } catch (RequestException $e) {
            Notification::make()->danger()->title('কানেক্ট হয়নি')
                ->body($e->response->json('error.message') ?? 'Pixel ID বা token ভুল।')
                ->send();
        } catch (Throwable) {
            Notification::make()->danger()->title('কানেক্ট হয়নি')->body('Meta-র সার্ভারে পৌঁছানো যায়নি, একটু পরে আবার চেষ্টা করুন।')->send();
        }
    }

    /** Accepts the whole `<meta …>` tag Search Console hands out, or just its content. */
    public static function verificationCode(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return preg_match('/content=["\']([^"\']+)["\']/i', $value, $m) ? $m[1] : trim(strip_tags($value));
    }

    private function secretInput(string $name, string $label, bool $stored): TextInput
    {
        $input = TextInput::make($name)->label($label)->password()->revealable()->maxLength(500)->autocomplete('off')
            ->placeholder($stored ? '•••••••• সেভ করা আছে — বদলাতে নতুনটা দিন' : null);

        if (! $stored) {
            return $input;
        }

        return $input->hintAction(Action::make("clear_{$name}")
            ->label('মুছে ফেলুন')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function () use ($name) {
                $settings = app(TrackingSettings::class);
                $settings->{$name} = null;
                $settings->save();
                Notification::make()->success()->title('মুছে ফেলা হয়েছে')->send();
            }));
    }
}

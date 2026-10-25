<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Shipping\Models\District;
use App\Filament\Pages\ManageTrackingSettings;
use App\Models\User;
use App\Settings\TrackingSettings;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    private Product $attar;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::create(['name' => 'আতর', 'name_en' => 'Attar', 'slug' => 'attar']);
        $this->attar = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'name_en' => 'Oud Attar', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 5, 'is_active' => true,
        ]);
    }

    private function configure(array $values): void
    {
        $settings = app(TrackingSettings::class);
        $settings->fill($values)->save();
    }

    private function enableServerEvents(): void
    {
        $this->configure([
            'meta_pixel_id' => '123456789012345', 'meta_capi_token' => 'meta-token',
            'ga4_measurement_id' => 'G-TEST123', 'ga4_api_secret' => 'ga-secret',
        ]);
    }

    public function test_public_config_never_exposes_secrets(): void
    {
        $this->enableServerEvents();
        $this->configure(['gtm_id' => 'GTM-ABC123', 'body_end_scripts' => '<script>console.log(1)</script>']);

        $response = $this->getJson('/api/v1/tracking')
            ->assertOk()
            ->assertJsonPath('gtm_id', 'GTM-ABC123')
            ->assertJsonPath('meta_pixel_id', '123456789012345')
            ->assertJsonPath('server_events', true)
            ->assertJsonPath('scripts.body_end', '<script>console.log(1)</script>');

        $this->assertStringNotContainsString('meta-token', $response->getContent());
        $this->assertStringNotContainsString('ga-secret', $response->getContent());
    }

    public function test_secrets_are_encrypted_at_rest(): void
    {
        $this->enableServerEvents();

        $stored = DB::table('settings')->where('group', 'tracking')->where('name', 'meta_capi_token')->value('payload');
        $this->assertStringNotContainsString('meta-token', $stored);
        $this->assertSame('meta-token', app(TrackingSettings::class)->refresh()->meta_capi_token);
    }

    public function test_placing_an_order_sends_purchase_to_meta_and_ga4_with_the_browser_event_id(): void
    {
        $this->seed(ShippingSeeder::class);
        $this->enableServerEvents();
        Http::fake();

        $this->postJson('/api/v1/orders', [
            'name' => 'Rahim Uddin',
            'phone' => '01712345678',
            'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০',
            'payment_method' => 'cod',
            'items' => [['product_id' => $this->attar->id, 'quantity' => 2]],
            'event_id' => 'evt-abc',
            'fbp' => 'fb.1.123.456',
            'ga_client_id' => '111.222',
        ])->assertCreated();

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'graph.facebook.com')) {
                return false;
            }
            $event = $request['data'][0];

            return str_contains($request->url(), '/123456789012345/events')
                && str_contains($request->url(), 'access_token=meta-token')
                && $event['event_name'] === 'Purchase'
                && $event['event_id'] === 'evt-abc'
                && $event['custom_data']['value'] == 960 // 900 + 60 delivery
                && $event['custom_data']['content_ids'] === [(string) $this->attar->id]
                && $event['user_data']['ph'] === [hash('sha256', '8801712345678')]
                && $event['user_data']['fbp'] === 'fb.1.123.456';
        });

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'google-analytics.com/mp/collect')
            && $request['client_id'] === '111.222'
            && $request['events'][0]['name'] === 'purchase'
            && $request['events'][0]['params']['items'][0]['item_name'] === 'Oud Attar');
    }

    public function test_nothing_is_sent_while_tracking_is_not_configured(): void
    {
        Http::fake();

        $this->postJson('/api/v1/tracking/events', [
            'name' => 'AddToCart', 'event_id' => 'evt-1', 'items' => [['product_id' => $this->attar->id, 'quantity' => 1]],
        ])->assertStatus(202);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'facebook') || str_contains($request->url(), 'google'));
        $this->getJson('/api/v1/tracking')->assertJsonPath('server_events', false);
    }

    public function test_browser_events_are_repriced_and_sent_to_meta_only(): void
    {
        $this->enableServerEvents();
        Http::fake();

        $this->postJson('/api/v1/tracking/events', [
            'name' => 'AddToCart', 'event_id' => 'evt-cart', 'url' => 'http://store.test/p/oud-attar',
            'items' => [['product_id' => $this->attar->id, 'quantity' => 3]],
        ])->assertStatus(202);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'google-analytics'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'graph.facebook.com')
            && $request['data'][0]['event_name'] === 'AddToCart'
            && $request['data'][0]['event_id'] === 'evt-cart'
            && $request['data'][0]['custom_data']['value'] == 1350);

        $this->postJson('/api/v1/tracking/events', ['name' => 'Purchase', 'event_id' => 'x', 'items' => [['product_id' => $this->attar->id, 'quantity' => 1]]])
            ->assertJsonValidationErrors('name');
    }

    public function test_admin_saves_ids_and_blank_secret_keeps_the_stored_one(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
        $this->enableServerEvents();

        Livewire::test(ManageTrackingSettings::class)
            ->assertSet('data.meta_capi_token', null) // never sent to the browser
            ->set('data.gtm_id', 'gtm-abc123')
            ->set('data.gsc_verification', '<meta name="google-site-verification" content="abc_XYZ-123" />')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(TrackingSettings::class)->refresh();
        $this->assertSame('GTM-ABC123', $settings->gtm_id);
        $this->assertSame('abc_XYZ-123', $settings->gsc_verification);
        $this->assertSame('meta-token', $settings->meta_capi_token);

        Livewire::test(ManageTrackingSettings::class)
            ->set('data.ga4_measurement_id', 'UA-1234')
            ->call('save')
            ->assertHasErrors(['data.ga4_measurement_id']);
    }

    public function test_meta_connection_test_reports_the_pixel_name(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));
        $this->enableServerEvents();
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '123456789012345', 'name' => 'Looks Jhorna Pixel'])]);

        Livewire::test(ManageTrackingSettings::class)
            ->call('testMeta')
            ->assertNotified('কানেক্টেড: Looks Jhorna Pixel');
    }

    public function test_page_opens_on_a_fresh_install_with_no_secrets(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('super_admin'));

        $this->get('/admin/settings/tracking')->assertOk();
    }

    public function test_tracking_page_requires_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/settings/tracking')->assertForbidden();
    }

    public function test_purchase_goes_to_tiktok_events_api_with_hashed_phone_and_click_ids(): void
    {
        $this->seed(ShippingSeeder::class);
        $this->configure(['tiktok_pixel_id' => 'CABCDEFGHIJ123', 'tiktok_access_token' => 'tt-token']);
        Http::fake(['business-api.tiktok.com/*' => Http::response(['code' => 0, 'message' => 'OK'])]);

        $this->postJson('/api/v1/orders', [
            'name' => 'Rahim', 'phone' => '01712345678', 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod',
            'items' => [['product_id' => $this->attar->id, 'quantity' => 1]],
            'event_id' => 'evt-tt', 'ttp' => 'ttp-cookie', 'ttclid' => 'click-1',
        ])->assertCreated();

        Http::assertSent(function (Request $request) {
            $event = $request['data'][0] ?? null;

            return str_contains($request->url(), 'business-api.tiktok.com')
                && $request->header('Access-Token') === ['tt-token']
                && $request['event_source_id'] === 'CABCDEFGHIJ123'
                && $event['event'] === 'CompletePayment'
                && $event['event_id'] === 'evt-tt'
                && $event['user']['phone'] === hash('sha256', '+8801712345678')
                && $event['user']['ttp'] === 'ttp-cookie'
                && $event['user']['ttclid'] === 'click-1';
        });
    }

    public function test_page_scripts_are_public_only_when_enabled(): void
    {
        $this->configure([
            'tiktok_access_token' => 'tt-secret',
            'page_scripts' => [
                ['name' => 'Checkout tag', 'paths' => "/checkout\n /p/* ", 'placement' => 'head', 'code' => '<script>1</script>', 'enabled' => true],
                ['name' => 'Off', 'paths' => '/', 'placement' => 'body_end', 'code' => '<script>2</script>', 'enabled' => false],
            ],
        ]);

        $response = $this->getJson('/api/v1/tracking')
            ->assertOk()
            ->assertJsonCount(1, 'page_scripts')
            ->assertJsonPath('page_scripts.0.paths', ['/checkout', '/p/*'])
            ->assertJsonPath('page_scripts.0.placement', 'head');
        $this->assertStringNotContainsString('tt-secret', $response->getContent());
        $this->assertStringNotContainsString('Checkout tag', $response->getContent());
    }
}

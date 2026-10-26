<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\LandingPage;
use App\Domain\Marketing\Support\LandingBlocks;
use App\Domain\Marketing\Support\LandingTemplates;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use App\Filament\Resources\LandingPages\Pages\CreateLandingPage;
use App\Filament\Resources\LandingPages\Pages\ListLandingPages;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    private Product $attar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingSeeder::class);
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->attar = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 5, 'is_active' => true,
        ]);
    }

    private function page(array $blocks, array $attributes = []): LandingPage
    {
        return LandingPage::create(['slug' => 'eid-attar', 'title' => 'ঈদ আতর', 'product_id' => $this->attar->id, 'blocks' => $blocks, ...$attributes]);
    }

    public function test_api_resolves_blocks_and_appends_the_order_form(): void
    {
        $this->page([
            ['type' => 'hero', 'data' => ['enabled' => true, 'title' => 'খাঁটি উদ আতর', 'badge' => 'অফার', 'ends_at' => now()->addDay()->toDateTimeString()]],
            ['type' => 'video', 'data' => ['enabled' => true, 'url' => 'https://youtu.be/dQw4w9WgXcQ']],
            ['type' => 'faq', 'data' => ['enabled' => false, 'items' => [['question' => 'লুকানো?', 'answer' => 'হ্যাঁ']]]],
            ['type' => 'reviews', 'data' => ['enabled' => true, 'items' => [['name' => 'করিম', 'location' => 'ঢাকা', 'rating' => 9, 'text' => 'দারুণ']]]],
            ['type' => 'gallery', 'data' => ['enabled' => true, 'images' => []]], // empty → dropped
        ]);

        $this->getJson('/api/v1/landing-pages/eid-attar')
            ->assertOk()
            ->assertJsonPath('data.product.slug', 'oud-attar')
            ->assertJsonPath('data.product.price', 45000)
            ->assertJsonPath('data.indexable', false)
            ->assertJsonCount(4, 'data.blocks')
            ->assertJsonPath('data.blocks.0.badge', 'অফার')
            ->assertJsonPath('data.blocks.1.video_url', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0')
            ->assertJsonPath('data.blocks.2.items.0.rating', 5)
            ->assertJsonPath('data.blocks.2.items.0.meta', 'ঢাকা')
            ->assertJsonPath('data.blocks.3.type', 'order_form');

        $this->getJson('/api/v1/landing-pages')->assertJsonPath('data.0.slug', 'eid-attar');
    }

    public function test_hidden_product_or_page(): void
    {
        $page = $this->page([]);
        $this->attar->update(['is_active' => false]);
        $this->getJson('/api/v1/landing-pages/eid-attar')->assertOk()->assertJsonPath('data.product', null);

        $page->update(['is_active' => false]);
        $this->getJson('/api/v1/landing-pages/eid-attar')->assertNotFound();
    }

    public function test_video_links_become_embeds(): void
    {
        $this->assertSame('https://www.youtube-nocookie.com/embed/abcdefghijk?rel=0', LandingBlocks::embed('https://www.youtube.com/shorts/abcdefghijk'));
        $this->assertStringStartsWith('https://www.facebook.com/plugins/video.php', LandingBlocks::embed('https://www.facebook.com/page/videos/123'));
        $this->assertNull(LandingBlocks::embed('https://example.com/video.mp4'));
    }

    public function test_orders_remember_their_landing_page(): void
    {
        $page = $this->page([]);
        $payload = [
            'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod',
            'items' => [['product_id' => $this->attar->id, 'quantity' => 1]],
        ];

        $this->postJson('/api/v1/orders', [...$payload, 'landing_page_id' => $page->id])->assertCreated();
        $this->postJson('/api/v1/orders', [...$payload, 'phone' => '01812345678', 'landing_page_id' => 999])->assertCreated();

        $this->assertSame([$page->id, null], Order::orderBy('id')->pluck('landing_page_id')->all());
    }

    public function test_package_sets_the_price_of_its_quantity(): void
    {
        // ৳450 each; "২ সেট" is two for ৳800, "৩ সেট" costs more than three singles so it saves nothing.
        $page = $this->page([['type' => 'order_form', 'data' => ['enabled' => true, 'packages' => [
            ['label' => '১ সেট', 'quantity' => 1, 'price' => 45000],
            ['label' => '২ সেট', 'badge' => 'সেরা সেভিংস', 'quantity' => 2, 'price' => 80000],
            ['label' => '৩ সেট', 'quantity' => 3, 'price' => 200000],
            ['label' => '', 'quantity' => 1, 'price' => 100], // unnamed → dropped
        ]]]]);
        $dhaka = District::where('name_en', 'Dhaka')->value('id');

        $this->getJson('/api/v1/landing-pages/eid-attar')
            ->assertJsonCount(3, 'data.blocks.0.packages')
            ->assertJsonPath('data.blocks.0.packages.1', ['index' => 1, 'label' => '২ সেট', 'badge' => 'সেরা সেভিংস', 'quantity' => 2, 'price' => 80000]);

        $quote = fn (int $quantity, ?int $package) => $this->postJson('/api/v1/cart/quote', [
            'items' => [['product_id' => $this->attar->id, 'quantity' => $quantity]], 'landing_page_id' => $page->id, 'package' => $package,
        ])->assertOk()->json('data');

        $this->assertSame(['subtotal' => 90000, 'discount_total' => 10000], array_intersect_key($quote(2, 1), ['subtotal' => 0, 'discount_total' => 0]));
        $this->assertSame([['type' => 'package', 'label' => '২ সেট', 'amount' => 10000]], $quote(2, 1)['discounts']);
        $this->assertSame(0, $quote(3, 1)['discount_total']); // quantity doesn't match the package
        $this->assertSame(0, $quote(3, 2)['discount_total']); // package dearer than buying singly
        $this->assertSame(0, $quote(2, 9)['discount_total']); // unknown package

        $this->postJson('/api/v1/orders', [
            'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => $dhaka, 'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod',
            'items' => [['product_id' => $this->attar->id, 'quantity' => 2]], 'landing_page_id' => $page->id, 'package' => 1,
        ])->assertCreated();

        $order = Order::sole();
        $this->assertSame([90000, 10000], [$order->subtotal, $order->discount]);
        $this->assertSame('package', $order->discounts[0]['type']);
        $this->assertSame(2, $order->items->sole()->quantity);
        $this->assertSame(3, $this->attar->fresh()->stock);
    }

    public function test_admin_builds_a_page_from_the_template(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('marketer'));

        $this->get('/admin/landing-pages')->assertOk();
        // Design and block pickers show their preview screenshots.
        $this->get('/admin/landing-pages/create')->assertOk()
            ->assertSee('images/landing-previews/designs/showcase.jpg', false)
            ->assertSee('images/landing-previews/blocks/offer.jpg', false);

        Livewire::test(CreateLandingPage::class)
            ->fillForm(['title' => 'ঈদ অফার', 'slug' => 'eid-offer', 'product_id' => $this->attar->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = LandingPage::firstWhere('slug', 'eid-offer');
        $this->assertTrue(array_is_list($page->blocks));
        $this->assertSame(['hero', 'benefits', 'order_form', 'faq'], array_column($page->blocks, 'type'));
        $this->get("/admin/landing-pages/{$page->id}/edit")->assertOk();

        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));
        $this->get('/admin/landing-pages')->assertForbidden();
    }

    public function test_every_ready_made_design_creates_a_page_with_its_starting_blocks(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('marketer'));

        foreach (array_keys(LandingTemplates::TEMPLATES) as $key) {
            Livewire::test(CreateLandingPage::class)
                ->fillForm(['title' => "অফার {$key}", 'slug' => "offer-{$key}", 'product_id' => $this->attar->id])
                ->fillForm(['template' => $key]) // picking a design swaps in its blocks
                ->call('create')
                ->assertHasNoFormErrors();

            $page = LandingPage::firstWhere('slug', "offer-{$key}");
            $this->assertSame($key, $page->template);
            $this->assertSame(array_column(LandingTemplates::blocks($key), 'type'), array_column($page->blocks, 'type'));

            // Empty media blocks (gallery, video, reviews) stay off the page until filled in.
            $types = array_column($this->getJson("/api/v1/landing-pages/offer-{$key}")->assertOk()->assertJsonPath('data.template', $key)->json('data.blocks'), 'type');
            $this->assertNotContains('gallery', $types);
            $this->assertContains('order_form', $types);
        }
    }

    public function test_offer_notice_and_list_blocks_resolve(): void
    {
        $this->page([
            ['type' => 'announcement', 'data' => ['enabled' => true, 'title' => 'সারা দেশে ক্যাশ অন ডেলিভারি']],
            ['type' => 'announcement', 'data' => ['enabled' => true, 'title' => '']], // empty → dropped
            ['type' => 'trust', 'data' => ['enabled' => true, 'items' => [['icon' => 'truck', 'title' => 'দ্রুত ডেলিভারি']]]],
            ['type' => 'offer', 'data' => ['enabled' => true, 'badge' => '৫৮% ছাড়', 'ends_at' => now()->addDay()->toDateTimeString()]],
            ['type' => 'notice', 'data' => ['enabled' => true, 'title' => 'বিশেষ দ্রষ্টব্য', 'items' => [['title' => 'আগে টাকা লাগবে না']]]],
            ['type' => 'checklist', 'data' => ['enabled' => true, 'items' => []]], // empty → dropped
            ['type' => 'features', 'data' => ['enabled' => true, 'badge' => 'কেন এই পণ্য', 'items' => [['title' => 'মান', 'text' => "নরম\nটেকসই"]]]],
            ['type' => 'call', 'data' => ['enabled' => true, 'title' => 'ফোনে অর্ডার করুন']],
        ]);

        $blocks = $this->getJson('/api/v1/landing-pages/eid-attar')->assertOk()->json('data.blocks');

        $this->assertSame(['announcement', 'trust', 'offer', 'notice', 'features', 'call', 'order_form'], array_column($blocks, 'type'));
        $this->assertSame('দ্রুত ডেলিভারি', $blocks[1]['items'][0]['title']);
        $this->assertNotNull($blocks[2]['ends_at']);
        $this->assertSame('আগে টাকা লাগবে না', $blocks[3]['items'][0]['title']);
        $this->assertSame("নরম\nটেকসই", $blocks[4]['items'][0]['text']);
        $this->assertSame('কেন এই পণ্য', $blocks[4]['badge']);
    }

    public function test_unknown_design_falls_back_to_classic(): void
    {
        $this->page([], ['template' => 'gone']);
        $this->getJson('/api/v1/landing-pages/eid-attar')->assertJsonPath('data.template', 'classic');
    }

    public function test_ab_variant_is_split_counted_and_can_be_promoted(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(tap(User::factory()->create())->assignRole('marketer'));
        $main = $this->page([['type' => 'hero', 'data' => ['enabled' => true, 'title' => 'A']]]);

        $this->getJson('/api/v1/landing-pages/experiments')->assertOk()->assertExactJson(['data' => []]);

        Livewire::test(ListLandingPages::class)->callTableAction('variant', $main);
        $variant = LandingPage::where('variant_of_id', $main->id)->sole();
        $this->assertFalse($variant->is_active);
        $variant->update(['is_active' => true, 'traffic_weight' => 30, 'blocks' => [['type' => 'hero', 'data' => ['enabled' => true, 'title' => 'B']]]]);

        $this->getJson('/api/v1/landing-pages/experiments')
            ->assertJsonPath('data.eid-attar.0', ['slug' => 'eid-attar', 'weight' => 50])
            ->assertJsonPath('data.eid-attar.1', ['slug' => $variant->slug, 'weight' => 30]);
        $this->getJson('/api/v1/landing-pages')->assertJsonCount(1, 'data'); // sitemap: main page only
        $this->getJson("/api/v1/landing-pages/{$variant->slug}")->assertJsonPath('data.variant_of', 'eid-attar')->assertJsonPath('data.indexable', false);

        $this->postJson("/api/v1/landing-pages/{$variant->slug}/view")->assertNoContent();
        $this->assertSame(1, $variant->fresh()->views);

        Livewire::test(ListLandingPages::class)->callTableAction('promote', $variant);
        $this->assertSame('B', $main->fresh()->blocks[0]['data']['title']);
        $this->assertFalse($variant->fresh()->is_active);
    }
}

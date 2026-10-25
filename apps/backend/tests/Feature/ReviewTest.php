<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Actions\PlaceOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Models\Review;
use App\Domain\Shipping\Models\District;
use App\Filament\Resources\Reviews\Pages\ManageReviews;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private Product $attar;

    private Product $tasbih;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingSeeder::class);
        Storage::fake('public');

        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $make = fn (string $slug) => Product::create([
            'category_id' => $category->id, 'name' => $slug, 'slug' => $slug,
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 10, 'is_active' => true,
        ]);
        $this->attar = $make('attar');
        $this->tasbih = $make('tasbih');

        $order = app(PlaceOrder::class)->handle([
            'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod',
            'items' => [['product_id' => $this->attar->id, 'quantity' => 1]],
        ]);
        $order->update(['status' => OrderStatus::Delivered]);
        $this->customer = Customer::firstWhere('phone', '01712345678');
    }

    public function test_only_received_products_can_be_reviewed_once(): void
    {
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/v1/customer/reviewable')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'attar');

        $this->postJson('/api/v1/customer/reviews', ['product_id' => $this->tasbih->id, 'rating' => 5])->assertJsonValidationErrors('product_id');

        $this->post('/api/v1/customer/reviews', [
            'product_id' => $this->attar->id, 'rating' => 4, 'body' => 'ঘ্রাণ অনেকক্ষণ থাকে',
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.verified', true);

        $review = Review::first();
        $this->assertSame(ReviewStatus::Pending, $review->status);
        Storage::disk('public')->assertExists($review->images[0]);
        $this->postJson('/api/v1/customer/reviews', ['product_id' => $this->attar->id, 'rating' => 5])->assertJsonValidationErrors('product_id');
        $this->getJson('/api/v1/customer/reviewable')->assertJsonCount(0, 'data');
    }

    public function test_approved_reviews_show_and_update_the_rating(): void
    {
        $review = Review::create(['product_id' => $this->attar->id, 'customer_id' => $this->customer->id, 'name' => 'রহিম', 'rating' => 4, 'body' => 'ভালো']);
        Review::create(['product_id' => $this->attar->id, 'name' => 'করিম', 'rating' => 5, 'status' => ReviewStatus::Approved, 'source' => 'admin']);

        $this->getJson('/api/v1/products/attar/reviews')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products/attar')->assertJsonPath('data.reviews_count', 1)->assertJsonPath('data.rating_avg', 5);

        $review->update(['status' => ReviewStatus::Approved, 'reply' => 'ধন্যবাদ!']);

        $this->getJson('/api/v1/products/attar/reviews')->assertJsonCount(2, 'data')->assertJsonPath('data.1.reply', 'ধন্যবাদ!'); // newest first
        $this->getJson('/api/v1/products/attar')
            ->assertJsonPath('data.reviews_count', 2)
            ->assertJsonPath('data.rating_avg', 4.5)
            ->assertJsonPath('data.rating_breakdown.0', ['stars' => 5, 'count' => 1])
            ->assertJsonPath('data.rating_breakdown.1', ['stars' => 4, 'count' => 1]);
        $this->assertNotNull($review->fresh()->replied_at);

        $review->delete();
        $this->assertSame(1, $this->attar->fresh()->reviews_count);
    }

    public function test_staff_moderate_reviews(): void
    {
        $this->seed(RoleSeeder::class);
        $review = Review::create(['product_id' => $this->attar->id, 'customer_id' => $this->customer->id, 'name' => 'রহিম', 'rating' => 3]);
        $this->actingAs(tap(User::factory()->create())->assignRole('catalog_manager'));

        Livewire::test(ManageReviews::class)
            ->assertCanSeeTableRecords([$review])
            ->callTableAction('approve', $review);

        $this->assertSame(ReviewStatus::Approved, $review->fresh()->status);
        $this->assertSame(3.0, $this->attar->fresh()->rating_avg);

        $this->actingAs(tap(User::factory()->create())->assignRole('order_manager'));
        Livewire::test(ManageReviews::class)->set('activeTab', 'all')->assertTableActionHidden('reject', $review);
    }
}

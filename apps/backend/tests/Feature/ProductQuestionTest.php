<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Review\Enums\QuestionStatus;
use App\Domain\Review\Models\ProductQuestion;
use App\Filament\Resources\ProductQuestions\Pages\ManageProductQuestions;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductQuestionTest extends TestCase
{
    use RefreshDatabase;

    private Product $attar;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->attar = Product::create([
            'category_id' => $category->id, 'name' => 'আতর', 'slug' => 'attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 10, 'is_active' => true,
        ]);
    }

    public function test_question_waits_until_answered(): void
    {
        $this->postJson('/api/v1/products/attar/questions', ['name' => 'করিম', 'phone' => '+880 1712-345678', 'question' => 'এটা কত মিলি?'])
            ->assertCreated();

        $q = ProductQuestion::firstOrFail();
        $this->assertSame('01712345678', $q->phone);
        $this->assertSame(QuestionStatus::Pending, $q->status);
        $this->getJson('/api/v1/products/attar/questions')->assertOk()->assertJsonCount(0, 'data');

        $q->update(['answer' => '৬ মিলি।']);
        $this->assertSame(QuestionStatus::Published, $q->fresh()->status);
        $this->assertNotNull($q->fresh()->answered_at);

        $this->getJson('/api/v1/products/attar/questions')
            ->assertOk()
            ->assertJsonPath('data.0.answer', '৬ মিলি।')
            ->assertJsonMissingPath('data.0.phone');
    }

    public function test_question_is_validated_and_bots_are_refused(): void
    {
        $this->postJson('/api/v1/products/attar/questions', ['name' => 'ক', 'question' => 'হ'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'question']);
        $this->postJson('/api/v1/products/attar/questions', ['name' => 'করিম', 'question' => 'এটা কত মিলি?', 'website' => 'x'])
            ->assertUnprocessable();
        $this->postJson('/api/v1/products/missing/questions', ['name' => 'করিম', 'question' => 'এটা কত মিলি?'])->assertNotFound();
        $this->assertSame(0, ProductQuestion::count());
    }

    public function test_staff_answer_from_admin(): void
    {
        $this->seed(RoleSeeder::class);
        $q = ProductQuestion::create(['product_id' => $this->attar->id, 'name' => 'করিম', 'question' => 'ডেলিভারি কত দিনে?', 'status' => QuestionStatus::Pending]);
        $user = tap(User::factory()->create())->assignRole('order_manager');
        $this->actingAs($user);

        Livewire::test(ManageProductQuestions::class)
            ->callTableAction('answer', $q, ['answer' => 'ঢাকায় ১-২ দিন।'])
            ->assertHasNoTableActionErrors();

        $q->refresh();
        $this->assertSame(QuestionStatus::Published, $q->status);
        $this->assertSame($user->id, $q->answered_by);
    }
}

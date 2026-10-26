<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BoughtTogetherTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_products_from_the_same_orders_most_shared_first(): void
    {
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $make = fn (string $slug, bool $active = true) => Product::create([
            'category_id' => $category->id, 'name' => $slug, 'slug' => $slug, 'type' => ProductType::Simple, 'price' => 10000, 'stock' => 5, 'is_active' => $active,
        ]);
        [$attar, $tasbih, $cap, $mat, $hidden] = [$make('attar'), $make('tasbih'), $make('cap'), $make('mat'), $make('hidden', false)];

        $order = function (array $products, OrderStatus $status = OrderStatus::Delivered) {
            $order = Order::create(['access_token' => Str::random(40), 'status' => $status, 'customer_name' => 'রহিম', 'customer_phone' => '01712345678', 'address' => 'মিরপুর', 'subtotal' => 0, 'total' => 0]);
            foreach ($products as $p) {
                $order->items()->create(['product_id' => $p->id, 'name' => $p->name, 'unit_price' => 10000, 'quantity' => 1, 'line_total' => 10000]);
            }
        };
        $order([$attar, $tasbih, $cap]);
        $order([$attar, $cap, $hidden]);
        $order([$attar, $mat], OrderStatus::Cancelled); // doesn't count
        $order([$tasbih, $mat]); // no attar

        $this->getJson('/api/v1/products/attar/bought-together')->assertOk()
            ->assertJsonPath('data.*.slug', ['cap', 'tasbih']);
        $this->getJson('/api/v1/products/mat/bought-together')->assertJsonPath('data.*.slug', ['tasbih']);
    }
}

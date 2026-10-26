<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Models\Order;
use App\Domain\Report\DateRange;
use App\Domain\Report\SalesReport;
use App\Domain\Shipping\Models\District;
use App\Filament\Pages\ManageCostSettings;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\User;
use App\Settings\CostSettings;
use App\Settings\ShippingSettings;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductCostTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingSeeder::class);
        $this->category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $settings = app(CostSettings::class);
        $settings->shared = [['label' => 'প্যাকেজিং', 'amount' => 1000]];
        $settings->save();
    }

    private function product(array $attributes): Product
    {
        return Product::create([
            'category_id' => $this->category->id, 'name' => 'উদ আতর', 'slug' => 'oud-'.uniqid(), 'type' => ProductType::Simple,
            'price' => 50000, 'cost_price' => 30000, 'stock' => 10, 'is_active' => true, ...$attributes,
        ]);
    }

    private function order(array $items): Order
    {
        $this->postJson('/api/v1/orders', [
            'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod', 'items' => $items,
        ])->assertCreated();

        return Order::latest('id')->first();
    }

    public function test_free_delivery_product_carries_its_own_costs_and_the_delivery_the_shop_pays(): void
    {
        $product = $this->product(['extra_costs' => [['label' => 'র‍্যাপিং', 'amount' => 2000]], 'free_delivery' => true, 'delivery_cost' => 8000]);

        $order = $this->order([['product_id' => $product->id, 'quantity' => 2]]);
        $item = $order->items->sole();

        $this->assertSame(0, $order->delivery_charge);
        $this->assertSame(8000, $order->delivery_cost);
        $this->assertSame([30000, 3000, 8000], [$item->unit_cost, $item->unit_extra_cost, $item->delivery_cost]); // র‍্যাপিং + প্যাকেজিং
        $this->assertSame(74000, $item->totalCost());

        $row = SalesReport::for(DateRange::from('today'))->products()[0];
        $this->assertSame([60000, 6000, 8000, 74000, 26000], [$row['purchase'], $row['extra'], $row['delivery'], $row['cost'], $row['profit']]);

        $profit = SalesReport::for(DateRange::from('today'))->profit();
        $this->assertSame([100000, 60000, 6000, 8000, 26000], [$profit['goods'], $profit['purchase'], $profit['extra'], $profit['delivery'], $profit['gross']]);
    }

    public function test_customer_paid_delivery_and_opt_outs_cost_the_shop_nothing(): void
    {
        $paid = $this->product(['shared_costs' => false]);
        $order = $this->order([['product_id' => $paid->id, 'quantity' => 1]]);
        $this->assertGreaterThan(0, $order->delivery_charge);
        $this->assertSame(0, $order->delivery_cost);
        $this->assertSame(0, $order->items->sole()->unit_extra_cost);

        $notMine = $this->product(['free_delivery' => true, 'owner_pays_delivery' => false]);
        $this->assertSame(0, $this->order([['product_id' => $notMine->id, 'quantity' => 1]])->delivery_cost);
    }

    public function test_threshold_free_delivery_costs_the_zone_charge_unless_turned_off(): void
    {
        $product = $this->product([]);
        $shipping = app(ShippingSettings::class);
        $shipping->free_delivery_threshold = 40000;
        $shipping->save();

        $order = $this->order([['product_id' => $product->id, 'quantity' => 1]]);
        $this->assertSame(0, $order->delivery_charge);
        $this->assertSame(6000, $order->delivery_cost); // Dhaka city zone

        $settings = app(CostSettings::class);
        $settings->owner_pays_free_delivery = false;
        $settings->save();
        $this->assertSame(0, $this->order([['product_id' => $product->id, 'quantity' => 1]])->delivery_cost);
    }

    public function test_admin_sets_costs_on_the_product_and_in_settings(): void
    {
        $this->actingAs(tap(User::factory()->create())->assignRole(Role::findOrCreate('super_admin', 'web')));
        $product = $this->product(['cost_price' => null]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['cost_price' => 250, 'extra_costs' => [['label' => 'ক্যারিং', 'amount' => 15]]])
            ->assertSee('৳275') // 250 + 15 + 10 shared
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame([25000, [['label' => 'ক্যারিং', 'amount' => 1500]]], [$product->fresh()->cost_price, $product->fresh()->extra_costs]);

        Livewire::test(ManageCostSettings::class)
            ->fillForm(['shared' => [['label' => 'ট্রান্সপোর্ট', 'amount' => 5]], 'owner_pays_free_delivery' => false])
            ->call('save')->assertHasNoFormErrors();
        $settings = app(CostSettings::class)->refresh();
        $this->assertSame([[['label' => 'ট্রান্সপোর্ট', 'amount' => 500]], false], [$settings->shared, $settings->owner_pays_free_delivery]);
    }
}

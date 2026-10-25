<?php

namespace Tests\Feature;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Enums\StockReason;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\StockMovementsRelationManager;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private Product $attar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ShippingSeeder::class, RoleSeeder::class]);
        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->attar = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 10, 'is_active' => true,
        ]);
    }

    public function test_sales_and_cancellations_are_logged_with_the_order(): void
    {
        $number = $this->postJson('/api/v1/orders', [
            'name' => 'রহিম', 'phone' => '01712345678', 'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'address' => 'বাড়ি ১২, রোড ৪, মিরপুর ১০', 'payment_method' => 'cod',
            'items' => [['product_id' => $this->attar->id, 'quantity' => 3]],
        ])->assertCreated()->json('data.number');
        $order = Order::firstWhere('number', $number);

        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled);

        $moves = StockMovement::where('product_id', $this->attar->id)->orderBy('id')->get();
        $this->assertSame([StockReason::Adjustment, StockReason::Sale, StockReason::Restock], $moves->pluck('reason')->all()); // opening stock first
        $this->assertSame([10, -3, 3], $moves->pluck('change')->all());
        $this->assertSame([10, 7, 10], $moves->pluck('balance')->all());
        $this->assertSame($order->id, $moves[1]->order_id);
    }

    public function test_purchase_order_is_received_into_stock(): void
    {
        $user = tap(User::factory()->create())->assignRole('catalog_manager');
        $this->actingAs($user);
        $supplier = Supplier::create(['name' => 'আতর হাউস']);

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm(['supplier_id' => $supplier->id, 'items' => [['product_id' => $this->attar->id, 'quantity' => 20, 'unit_cost' => 250]]])
            ->call('create')
            ->assertHasNoFormErrors();

        $po = PurchaseOrder::sole();
        $this->assertSame('PO-'.(1000 + $po->id), $po->number);
        $this->assertSame(500000, $po->total);

        Livewire::test(ListPurchaseOrders::class)->callTableAction('receive', $po);
        $this->assertSame(PurchaseStatus::Received, $po->fresh()->status);
        $this->assertSame(30, $this->attar->fresh()->stock);
        $this->assertSame(25000, $this->attar->fresh()->cost_price);
        $this->assertSame($user->id, StockMovement::where('reason', StockReason::Purchase)->sole()->user_id);

        Livewire::test(ListPurchaseOrders::class)->callTableAction('payment', $po, ['amount' => 1000]);
        $this->assertSame(100000, $po->fresh()->paid_amount);

        // Received orders can't be received twice.
        Livewire::test(ListPurchaseOrders::class)->assertTableActionHidden('receive', $po);
    }

    public function test_manual_adjustment_and_log_permissions(): void
    {
        $this->actingAs(tap(User::factory()->create())->assignRole('catalog_manager'));
        Livewire::test(StockMovementsRelationManager::class, ['ownerRecord' => $this->attar, 'pageClass' => EditProduct::class])
            ->callTableAction('adjust', data: ['change' => -2, 'note' => 'ড্যামেজ']);
        $this->assertSame(8, $this->attar->fresh()->stock);
        $this->assertSame('ড্যামেজ', StockMovement::latest('id')->first()->note);

        $this->actingAs(tap(User::factory()->create())->assignRole('warehouse'));
        Livewire::test(ListStockMovements::class)->assertOk()->assertCanSeeTableRecords(StockMovement::all());

        $this->actingAs(tap(User::factory()->create())->assignRole('marketer'));
        $this->get(ListStockMovements::getUrl())->assertForbidden();
    }
}

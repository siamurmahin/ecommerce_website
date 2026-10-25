<?php

namespace Tests\Feature\Admin;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Actions\PlaceOrder;
use App\Domain\Order\Enums\OrderSource;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\District;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, ShippingSeeder::class]);

        $category = Category::create(['name' => 'আতর', 'slug' => 'attar']);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'উদ আতর', 'slug' => 'oud-attar',
            'type' => ProductType::Simple, 'price' => 45000, 'stock' => 5, 'is_active' => true,
        ]);

        $this->order = app(PlaceOrder::class)->handle([
            'name' => 'রহিম', 'phone' => '01712345678', 'address' => 'মিরপুর ১০, ঢাকা',
            'district_id' => District::where('name_en', 'Dhaka')->value('id'),
            'payment_method' => 'cod',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ]);
    }

    private function staff(string $role): User
    {
        return tap(User::factory()->create())->assignRole($role);
    }

    public function test_super_admin_can_open_order_screens_and_invoice(): void
    {
        $this->actingAs($this->staff('super_admin'));

        $this->get('/admin/orders')->assertOk()->assertSee($this->order->number);
        $this->get("/admin/orders/{$this->order->number}")->assertOk()->assertSee('রহিম');
        $this->get("/admin/orders/{$this->order->number}/edit")->assertOk();
        $this->get('/admin/delivery-zones')->assertOk()->assertSee('ঢাকা সিটি');
        $this->get('/admin/invoices?orders='.$this->order->number)->assertOk()->assertSee('৳450');
    }

    public function test_order_manager_sees_orders_but_not_catalog(): void
    {
        $this->actingAs($this->staff('order_manager'));

        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/invoices?orders='.$this->order->number)->assertOk();
        // Read-only catalog and zones, so phone orders can be taken; no editing.
        $this->get('/admin/products')->assertOk();
        $this->get('/admin/products/create')->assertForbidden();
        $this->get('/admin/categories')->assertForbidden();
    }

    public function test_content_editor_cannot_see_orders_or_invoices(): void
    {
        $this->actingAs($this->staff('content_editor'));

        $this->get('/admin/orders')->assertForbidden();
        $this->get('/admin/invoices?orders='.$this->order->number)->assertForbidden();
    }

    public function test_guest_is_sent_to_login_for_invoices(): void
    {
        $this->get('/admin/invoices?orders='.$this->order->number)->assertRedirect('/admin/login');
    }

    public function test_trashing_an_order_returns_its_stock(): void
    {
        $this->assertSame(4, $this->product->fresh()->stock);

        $this->order->delete();
        $this->assertSame(5, $this->product->fresh()->stock);

        $this->order->restore();
        $this->assertSame(4, $this->product->fresh()->stock);
    }

    public function test_staff_can_take_a_phone_order_with_custom_charge_and_discount(): void
    {
        $this->actingAs($staff = $this->staff('order_manager'));
        $this->get('/admin/orders/create')->assertOk();

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'phone' => '01812345678', 'name' => 'করিম', 'address' => 'চকবাজার, চট্টগ্রাম',
                'district_id' => District::where('name_en', 'Chattogram')->value('id'),
                'source' => OrderSource::Facebook->value, 'status' => OrderStatus::Confirmed->value,
                'delivery_charge' => 100, 'discount' => 50, 'notify' => false,
                'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::where('customer_phone', '01812345678')->firstOrFail();
        $this->assertSame(OrderSource::Facebook, $order->source);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(90000 + 10000 - 5000, $order->total);
        $this->assertSame($staff->id, $order->statusHistories()->first()->user_id);
        $this->assertSame(2, $this->product->fresh()->stock); // 5 − 1 in setUp − 2 here
    }

    public function test_content_editor_cannot_create_orders(): void
    {
        $this->actingAs($this->staff('content_editor'));
        $this->get('/admin/orders/create')->assertForbidden();
    }
}

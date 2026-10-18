<?php

namespace Tests\Feature;

use App\Models\AbandonedCheckout;
use App\Models\Category;
use App\Models\DropshipSupplier;
use App\Models\InventoryAdjustment;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderInventoryTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Setting::put('fog_enabled', '0');
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'order-inventory'], ['name' => 'Order inventory', 'is_active' => true]);
        $supplier = DropshipSupplier::firstOrCreate(['key' => 'stock-vendor'], ['name' => 'Stock Vendor', 'driver_key' => 'manual', 'supplier_type' => 'local', 'is_active' => true]);
        return Product::create([...['category_id' => $category->id, 'supplier_id' => $supplier->id, 'name' => 'Warehouse mouse', 'regular_price' => 300, 'stock_quantity' => 5, 'is_published' => true], ...$attributes]);
    }

    private function customer(): array
    {
        return ['customer_name' => 'Samir Rahman', 'customer_phone' => '01711223344', 'shipping_address' => 'House 25, Road 4, Mirpur Dhaka', 'city' => 'Dhaka', 'shipping_zone' => 'inside_dhaka', 'payment_method' => 'cod'];
    }

    #[DataProvider('guestChannels')]
    public function test_guest_order_channels_write_supplier_stock_movements(string $channel): void
    {
        $product = $this->product();
        if ($channel === 'checkout') {
            $this->post('/cart/add', ['product_id' => $product->id, 'qty' => 2])->assertSessionHasNoErrors();
            $this->post('/checkout', $this->customer())->assertRedirect()->assertSessionHasNoErrors();
        } elseif ($channel === 'quick') {
            $this->postJson('/quick-order', [...$this->customer(), 'product_id' => $product->id, 'qty' => 2])->assertOk()->assertJsonPath('success', true);
        } else {
            LandingPage::create(['title' => 'Mouse campaign', 'slug' => 'mouse-campaign', 'design' => 'product', 'product_id' => $product->id, 'is_active' => true]);
            $this->postJson('/lp/mouse-campaign/order', [...$this->customer(), 'qty' => 2])->assertOk()->assertJsonPath('ok', true);
        }
        $order = Order::firstOrFail();
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_adjustments', ['product_id' => $product->id, 'supplier_id' => $product->supplier_id, 'movement_type' => 'stock_out', 'stock_scope' => 'local', 'stock_before' => 5, 'stock_after' => 3, 'change' => -2, 'user_id' => null, 'actor_name' => 'Guest checkout']);
        $this->assertStringContainsString($order->order_number, InventoryAdjustment::firstOrFail()->reference);
    }

    public static function guestChannels(): array
    {
        return [['checkout'], ['quick'], ['landing']];
    }

    #[DataProvider('adminChannels')]
    public function test_admin_order_channels_write_stock_movements(string $channel): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();
        if ($channel === 'manual') {
            $this->post('/admin/orders', [...$this->customer(), 'items' => [['product_id' => $product->id, 'qty' => 2]]])->assertRedirect()->assertSessionHasNoErrors();
        } elseif ($channel === 'add_items') {
            $order = Order::factory()->create(['status' => 'pending']);
            $this->post('/admin/orders/'.$order->id.'/items', ['items' => [['product_id' => $product->id, 'quantity' => 2]]])->assertRedirect()->assertSessionHasNoErrors();
        } else {
            $cart = AbandonedCheckout::create([...array_intersect_key($this->customer(), array_flip(['customer_name', 'customer_phone', 'shipping_address'])), 'session_id' => 'recover-stock', 'cart_total' => 600, 'cart_items' => [['product_id' => $product->id, 'qty' => 2]]]);
            $this->post('/admin/abandoned-checkouts/'.$cart->id.'/create-order')->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_adjustments', ['product_id' => $product->id, 'supplier_id' => $product->supplier_id, 'movement_type' => 'stock_out', 'stock_before' => 5, 'stock_after' => 3, 'user_id' => auth()->id()]);
    }

    public static function adminChannels(): array
    {
        return [['manual'], ['add_items'], ['recovered']];
    }

    public function test_cancellation_retains_original_supplier_and_releases_reservation_only_once(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();
        $originalSupplier = $product->supplier_id;
        $order = Order::factory()->create(['status' => 'pending']);
        $this->post('/admin/orders/'.$order->id.'/items', ['items' => [['product_id' => $product->id, 'quantity' => 2]]])->assertSessionHasNoErrors();
        $other = DropshipSupplier::create(['key' => 'changed-vendor', 'name' => 'New vendor', 'driver_key' => 'manual', 'supplier_type' => 'local', 'is_active' => true]);
        $product->update(['supplier_id' => $other->id]);

        $this->patch('/admin/orders/'.$order->id, ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->patch('/admin/orders/'.$order->id, ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $order->fresh()->restoreStock();
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertSame(1, InventoryAdjustment::where('movement_type', 'return_in')->count());
        $this->assertDatabaseHas('inventory_adjustments', ['product_id' => $product->id, 'supplier_id' => $originalSupplier, 'movement_type' => 'return_in', 'change' => 2, 'stock_before' => 3, 'stock_after' => 5]);
    }

    #[DataProvider('campaignAvailability')]
    public function test_campaign_cancellation_restores_only_stock_actually_reserved(int $available): void
    {
        $product = $this->product(['stock_quantity' => $available]);
        LandingPage::create(['title' => 'Partial campaign', 'slug' => 'partial', 'design' => 'product', 'product_id' => $product->id, 'is_active' => true]);
        $this->postJson('/lp/partial/order', [...$this->customer(), 'qty' => 4])->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $order = Order::firstOrFail();
        $order->restoreStock();
        $order->restoreStock();
        $this->assertSame($available, $product->fresh()->stock_quantity);
    }

    public static function campaignAvailability(): array
    {
        return [[2], [0]];
    }

    public function test_insufficient_order_stock_rolls_back_previous_items_and_ledger(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $first = $this->product(); $second = $this->product(['stock_quantity' => 1]);
        $order = Order::factory()->create(['status' => 'pending']);
        $this->postJson('/admin/orders/'.$order->id.'/items', ['items' => [['product_id' => $first->id, 'quantity' => 2], ['product_id' => $second->id, 'quantity' => 2]]])->assertUnprocessable();
        $this->assertSame(5, $first->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_adjustments', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_supplier_order_availability_does_not_enter_warehouse_movements(): void
    {
        $product = $this->product(['source_type' => 'dropshipping']);
        $order = Order::factory()->create();
        $item = $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 300, 'quantity' => 2, 'line_total' => 600]);
        $stock = app(InventoryStockService::class);
        $stock->reserveOrderStock(null, $product, $order, $item);
        $stock->reserveOrderStock(null, $product, $order, $item);
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_adjustments', ['movement_type' => 'stock_out', 'stock_scope' => 'supplier', 'change' => -2]);
        $order->restoreStock();
        $this->assertDatabaseHas('inventory_adjustments', ['movement_type' => 'return_in', 'stock_scope' => 'supplier', 'change' => 2]);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminOrderItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Setting::put('tax_percent', '10');
        Setting::put('currency_symbol', '৳');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'test-products'], ['name' => 'Test Products', 'is_active' => true]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Extra Product',
            'regular_price' => 250,
            'stock_quantity' => 10,
            'is_published' => true,
        ], $attributes));
    }

    public function test_admin_adds_products_only_to_selected_pending_order_and_invoice(): void
    {
        $order = Order::factory()->create(['discount_amount' => 100, 'tax' => 90, 'total' => 1050]);
        $otherOrder = Order::factory()->create();
        $product = $this->product(['sale_price' => 200, 'sku' => 'EXTRA-1']);
        $product->images()->create(['path' => 'products/extra.jpg', 'is_primary' => true, 'position' => 0]);
        $original = $order->items()->create(['product_name' => 'Original Product', 'unit_price' => 1000, 'quantity' => 1, 'line_total' => 1000]);

        $this->from("/admin/orders/{$order->id}")
            ->post("/admin/orders/{$order->id}/items", [
                'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 1]],
                'total' => 1,
            ])
            ->assertRedirect("/admin/orders/{$order->id}")
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('1400.00', $order->subtotal);
        $this->assertSame('100.00', $order->discount_amount);
        $this->assertSame('60.00', $order->shipping_charge);
        $this->assertSame('130.00', $order->tax);
        $this->assertSame('1490.00', $order->total);
        $this->assertSame('pending', $order->status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame('1000.00', $original->fresh()->line_total);
        $this->assertSame('1060.00', $otherOrder->fresh()->total);
        $this->assertSame(0, $otherOrder->items()->count());
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id, 'product_id' => $product->id,
            'product_name' => 'Extra Product', 'image' => 'products/extra.jpg',
            'quantity' => 2, 'unit_price' => 200, 'line_total' => 400,
        ]);
        $this->get("/admin/orders/{$order->id}/invoice")
            ->assertOk()->assertSee('Extra Product')->assertSee('৳1,490.00', false);
    }

    #[DataProvider('nonPendingStatuses')]
    public function test_non_pending_orders_reject_product_additions(string $status): void
    {
        $order = Order::factory()->create(['status' => $status]);
        $product = $this->product();
        $this->postJson("/admin/orders/{$order->id}/items", ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame(0, $order->items()->count());
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame('1060.00', $order->fresh()->total);
    }

    public static function nonPendingStatuses(): array
    {
        return array_map(fn ($status) => [$status], ['confirmed', 'processing', 'shipped', 'delivered', 'cancelled']);
    }

    public function test_insufficient_stock_rolls_back_all_additions(): void
    {
        $order = Order::factory()->create();
        $first = $this->product(['name' => 'Available Product']);
        $second = $this->product(['name' => 'Limited Product', 'stock_quantity' => 1]);

        $this->postJson("/admin/orders/{$order->id}/items", ['items' => [
            ['product_id' => $first->id, 'quantity' => 2],
            ['product_id' => $second->id, 'quantity' => 2],
        ]])->assertUnprocessable()->assertJsonValidationErrors('items.1.quantity');

        $this->assertSame(0, $order->items()->count());
        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(1, $second->fresh()->stock_quantity);
        $this->assertSame('1000.00', $order->fresh()->subtotal);
        $this->assertSame('1060.00', $order->fresh()->total);
    }

    public function test_unpublished_product_cannot_be_added(): void
    {
        $order = Order::factory()->create();
        $product = $this->product(['is_published' => false]);
        $this->postJson("/admin/orders/{$order->id}/items", ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
        $this->assertSame(0, $order->items()->count());
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_duplicate_products_are_rejected_instead_of_overselling(): void
    {
        $order = Order::factory()->create();
        $product = $this->product();
        $this->postJson("/admin/orders/{$order->id}/items", ['items' => [
            ['product_id' => $product->id, 'quantity' => 6],
            ['product_id' => $product->id, 'quantity' => 6],
        ]])->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
        $this->assertSame(0, $order->items()->count());
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_are_rejected(mixed $quantity): void
    {
        $order = Order::factory()->create();
        $product = $this->product();
        $this->postJson("/admin/orders/{$order->id}/items", ['items' => [['product_id' => $product->id, 'quantity' => $quantity]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        $this->assertSame(0, $order->items()->count());
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public static function invalidQuantities(): array
    {
        return [[0], [-1], [1.5], ['invalid'], [100001]];
    }

    public function test_adding_same_product_keeps_the_original_purchase_price(): void
    {
        $order = Order::factory()->create();
        $product = $this->product();
        $original = $order->items()->create(['product_id' => $product->id, 'product_name' => 'Old Name', 'unit_price' => 100, 'quantity' => 10, 'line_total' => 1000]);

        $this->post("/admin/orders/{$order->id}/items", ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $order->items()->count());
        $this->assertSame('100.00', $original->fresh()->unit_price);
        $this->assertSame(10, $original->fresh()->quantity);
        $this->assertSame('1250.00', $order->fresh()->subtotal);
        $this->assertSame(9, $product->fresh()->stock_quantity);
    }

    public function test_search_returns_images_prices_and_stock_for_product_name_and_sku(): void
    {
        $product = $this->product(['sku' => 'SKU-EXTRA', 'sale_price' => 199.99]);
        $product->images()->create(['path' => 'https://example.test/extra.jpg', 'is_primary' => true, 'position' => 0]);
        foreach (['Extra Product', 'SKU-EXTRA'] as $search) {
            $this->getJson('/admin/orders/product-search?q='.urlencode($search))->assertOk()
                ->assertJsonPath('0.id', $product->id)
                ->assertJsonPath('0.price', 199.99)
                ->assertJsonPath('0.stock_quantity', 10)
                ->assertJsonPath('0.image_url', 'https://example.test/extra.jpg');
        }
    }

    public function test_customers_cannot_add_products_through_admin_endpoint(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::factory()->create(['user_id' => $customer->id]);
        $product = $this->product();
        $this->actingAs($customer)->post("/admin/orders/{$order->id}/items", ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertRedirect(route('admin.login'));
        $this->assertSame(0, $order->items()->count());
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryAdjustment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderSourcingStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    /** @return array{Product, Order, OrderItem} */
    private function pendingItem(int $quantity = 3, int $stock = 0): array
    {
        $category = Category::create(['name' => 'Clocks', 'slug' => 'clocks']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Sourced clock', 'regular_price' => 585, 'stock_quantity' => $stock, 'source_type' => 'dropshipping', 'allow_backorder' => true]);
        $order = Order::factory()->create(['status' => 'pending']);
        $item = $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'variant' => 'Color: Black', 'quantity' => $quantity, 'unit_price' => 585, 'line_total' => 585 * $quantity]);
        app(InventoryStockService::class)->reserveOrderStock(auth()->user(), $product, $order, $item);

        return [$product->fresh(), $order->fresh(), $item->fresh()];
    }

    private function receiptUrl(Order $order, OrderItem $item): string
    {
        return '/admin/orders/'.$order->id.'/items/'.$item->id.'/receive-stock';
    }

    public function test_receipts_allocate_only_received_units_and_unlock_confirmation_when_complete(): void
    {
        [$product, $order, $item] = $this->pendingItem();
        $url = $this->receiptUrl($order, $item);

        $this->from('/admin/orders/'.$order->id)->post($url, ['quantity' => 2, 'expected_outstanding' => 3, 'reference' => 'Purchase PO-12'])
            ->assertRedirect('/admin/orders/'.$order->id)->assertSessionHasNoErrors();

        $this->assertSame(1, $item->fresh()->sourcing_outstanding);
        $this->assertSame(2, $item->fresh()->reserved_stock_quantity);
        $this->assertSame(2, $item->fresh()->reserved_sourced_quantity);
        $this->assertSame('Color: Black', $item->fresh()->variant);
        $this->assertTrue($order->fresh()->awaiting_sourcing);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(0, $product->fresh()->sourced_stock_quantity);
        $this->assertDatabaseHas('inventory_adjustments', ['product_id' => $product->id, 'stock_scope' => 'local', 'movement_type' => 'stock_in', 'change' => 2, 'reason' => 'Physical stock received for order: Purchase PO-12']);
        $this->patchJson('/admin/orders/'.$order->id, ['status' => 'confirmed'])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->post($url, ['quantity' => 1, 'expected_outstanding' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($order->fresh()->awaiting_sourcing);
        $this->assertSame(0, $item->fresh()->sourcing_outstanding);
        $this->assertSame('pending', $order->fresh()->status);
        $this->patch('/admin/orders/'.$order->id, ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_repeated_or_stale_receipts_do_not_invent_or_double_allocate_stock(): void
    {
        [$product, $order, $item] = $this->pendingItem();
        $url = $this->receiptUrl($order, $item);
        $payload = ['quantity' => 2, 'expected_outstanding' => 3];
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $movements = InventoryAdjustment::count();

        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertSame($movements, InventoryAdjustment::count());
        $this->assertSame(2, $item->fresh()->reserved_stock_quantity);
        $this->assertSame(0, $product->fresh()->availableStockQuantity());
    }

    public function test_unsourced_cancellation_never_adds_stock_and_cannot_receive_goods(): void
    {
        [$product, $order, $item] = $this->pendingItem();

        $this->patch('/admin/orders/'.$order->id, ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $order->fresh()->restoreStock();

        $this->assertSame(0, $product->fresh()->availableStockQuantity());
        $this->assertFalse($order->fresh()->awaiting_sourcing);
        $this->postJson($this->receiptUrl($order, $item), ['quantity' => 1, 'expected_outstanding' => 3])->assertUnprocessable();
        $this->assertSame(0, InventoryAdjustment::where('stock_scope', 'local')->where('movement_type', 'stock_in')->count());
    }

    public function test_cancellation_restores_supplier_and_locally_received_allocations_to_their_own_balances_once(): void
    {
        [$product, $order, $item] = $this->pendingItem(3, 1);
        $this->post($this->receiptUrl($order, $item), ['quantity' => 1, 'expected_outstanding' => 2])->assertSessionHasNoErrors();

        $this->patch('/admin/orders/'.$order->id, ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $order->fresh()->restoreStock();

        $this->assertSame(1, $product->fresh()->stock_quantity);
        $this->assertSame(1, $product->fresh()->sourced_stock_quantity);
        $this->assertDatabaseHas('inventory_adjustments', ['movement_type' => 'return_in', 'stock_scope' => 'local', 'change' => 1]);
        $this->assertDatabaseHas('inventory_adjustments', ['movement_type' => 'return_in', 'stock_scope' => 'supplier', 'change' => 1]);
    }

    public function test_local_stock_remains_orderable_after_the_override_is_disabled(): void
    {
        [$product, $order, $item] = $this->pendingItem(1);
        $this->post($this->receiptUrl($order, $item), ['quantity' => 1, 'expected_outstanding' => 1])->assertSessionHasNoErrors();
        $order->restoreStock();
        $product->update(['allow_backorder' => false]);
        $product->refresh();
        $this->assertTrue($product->canOrderQuantity(1));
        $this->assertFalse($product->canOrderQuantity(2));
        $second = Order::factory()->create();
        $nextItem = $second->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 585, 'line_total' => 585]);

        app(InventoryStockService::class)->reserveOrderStock(auth()->user(), $product, $second, $nextItem);

        $this->assertSame(0, $product->fresh()->availableStockQuantity());
        $this->assertSame(1, $nextItem->fresh()->reserved_sourced_quantity);
        $this->assertFalse($second->fresh()->awaiting_sourcing);
    }

    public function test_receipt_rejects_an_item_from_another_order(): void
    {
        [$product, $order, $item] = $this->pendingItem();
        $otherOrder = Order::factory()->create();

        $this->postJson($this->receiptUrl($otherOrder, $item), ['quantity' => 1, 'expected_outstanding' => 3])->assertNotFound();

        $this->assertSame(0, $product->fresh()->availableStockQuantity());
        $this->assertSame(3, $item->fresh()->sourcing_outstanding);
    }

    #[DataProvider('receiptPermissions')]
    public function test_receiving_requires_both_order_and_inventory_write_permissions(array $permissions): void
    {
        [$product, $order, $item] = $this->pendingItem();
        $this->actingAs(User::factory()->create(['role' => 'inventory', 'permissions' => $permissions]));

        $this->postJson($this->receiptUrl($order, $item), ['quantity' => 1, 'expected_outstanding' => 3])->assertForbidden();

        $this->assertSame(3, $item->fresh()->sourcing_outstanding);
        $this->assertSame(0, $product->fresh()->availableStockQuantity());
    }

    public static function receiptPermissions(): array
    {
        return [[['orders.manage']], [['products.create']], [[]]];
    }

    public function test_receipt_validates_positive_quantity_and_reference_length(): void
    {
        [$product, $order, $item] = $this->pendingItem();

        $this->postJson($this->receiptUrl($order, $item), ['quantity' => 0, 'expected_outstanding' => 3, 'reference' => str_repeat('x', 121)])
            ->assertUnprocessable()->assertJsonValidationErrors(['quantity', 'reference']);

        $this->assertSame(3, $item->fresh()->sourcing_outstanding);
        $this->assertSame(0, $product->fresh()->availableStockQuantity());
    }

    public function test_unsourced_order_cannot_be_sent_to_a_courier(): void
    {
        [, $order] = $this->pendingItem();
        Http::fake();

        $this->postJson('/admin/orders/'.$order->id.'/send-to-courier', ['courier_provider' => 'steadfast', 'cod_amount' => 585])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        Http::assertNothingSent();
        $this->assertNull($order->fresh()->courier_consignment_id);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_cancelled_sourcing_order_cannot_reuse_released_allocations(): void
    {
        [$product, $order, $item] = $this->pendingItem(1);
        $this->post($this->receiptUrl($order, $item), ['quantity' => 1, 'expected_outstanding' => 1])->assertSessionHasNoErrors();
        $this->patch('/admin/orders/'.$order->id, ['status' => 'cancelled'])->assertSessionHasNoErrors();
        Http::fake();

        $this->patchJson('/admin/orders/'.$order->id, ['status' => 'pending'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patchJson('/admin/orders/'.$order->id, ['status' => 'confirmed'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson('/admin/orders/'.$order->id.'/send-to-courier', ['courier_provider' => 'steadfast', 'cod_amount' => 585])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        Http::assertNothingSent();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(1, $product->fresh()->sourced_stock_quantity);
    }

    public function test_received_source_order_must_be_confirmed_before_courier_dispatch(): void
    {
        [, $order, $item] = $this->pendingItem(1);
        $this->post($this->receiptUrl($order, $item), ['quantity' => 1, 'expected_outstanding' => 1])->assertSessionHasNoErrors();
        Http::fake();

        $this->postJson('/admin/orders/'.$order->id.'/send-to-courier', ['courier_provider' => 'steadfast', 'cod_amount' => 585])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        Http::assertNothingSent();
        $this->assertSame('pending', $order->fresh()->status);
    }
}

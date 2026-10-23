<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryAdjustment;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryStockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OrderSourcingAtomicityTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('releaseChannels')]
    public function test_failed_order_cancellation_or_deletion_rolls_back_all_stock_releases(string $channel): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        [$product, $order] = $this->partiallyReceivedOrder();
        $event = in_array($channel, ['delete', 'bulk_delete'], true) ? 'deleting' : 'updating';
        Event::listen('eloquent.'.$event.': '.Order::class, function (Order $updating) use ($order, $event): void {
            if ($updating->id === $order->id && ($event === 'deleting' || $updating->status === 'cancelled')) {
                throw new RuntimeException('The order change could not be saved.');
            }
        });
        Exceptions::fake([RuntimeException::class]);

        $this->release($channel, $order)->assertStatus(500);

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(0, $product->fresh()->sourced_stock_quantity);
        $this->assertSame(0, InventoryAdjustment::where('movement_type', 'return_in')->count());
        $this->assertSame(2, $order->items()->sole()->reserved_stock_quantity);
        Exceptions::assertReported(RuntimeException::class);
    }

    public static function releaseChannels(): array
    {
        return [['reject'], ['delete'], ['bulk_reject'], ['bulk_delete'], ['webhook']];
    }

    public function test_verification_uses_current_sourcing_items_instead_of_a_loaded_order_snapshot(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending']);
        $staleOrder = $order->load('items');
        $category = Category::create(['name' => 'Clocks', 'slug' => 'clocks']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Sourced clock', 'regular_price' => 300, 'stock_quantity' => 0, 'allow_backorder' => true]);
        $item = $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 300, 'line_total' => 300]);
        app(InventoryStockService::class)->reserveOrderStock(auth()->user(), $product, $order, $item);
        Route::bind('order', fn () => $staleOrder);

        $this->post('/admin/orders/'.$order->id.'/verify')->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('verified', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertTrue($order->fresh()->awaiting_sourcing);
    }

    /** @return array{Product, Order} */
    private function partiallyReceivedOrder(): array
    {
        $category = Category::create(['name' => 'Clocks', 'slug' => 'clocks']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Sourced clock', 'regular_price' => 300, 'stock_quantity' => 1, 'source_type' => 'dropshipping', 'allow_backorder' => true]);
        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending', 'courier_tracking_code' => 'sourcing-atomicity']);
        $item = $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 3, 'unit_price' => 300, 'line_total' => 900]);
        $stock = app(InventoryStockService::class);
        $stock->reserveOrderStock(auth()->user(), $product, $order, $item);
        $stock->receiveForOrder(auth()->user(), $order, $item, 1, 2);

        return [$product, $order];
    }

    private function release(string $channel, Order $order): TestResponse
    {
        if ($channel === 'webhook') {
            config(['services.courier_webhook_secret' => null]);

            return $this->postJson('/webhooks/courier-status', ['tracking_code' => 'sourcing-atomicity', 'status' => 'Cancelled']);
        }
        if (str_starts_with($channel, 'bulk_')) {
            return $this->postJson('/admin/orders/bulk', ['ids' => [$order->id], 'bulk_action' => substr($channel, 5)]);
        }
        if ($channel === 'delete') {
            return $this->deleteJson('/admin/orders/'.$order->id);
        }

        return $this->postJson('/admin/orders/'.$order->id.'/reject');
    }
}

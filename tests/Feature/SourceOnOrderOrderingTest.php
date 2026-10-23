<?php

namespace Tests\Feature;

use App\Models\AbandonedCheckout;
use App\Models\Category;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SourceOnOrderOrderingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Setting::put('fog_enabled', '0');
    }

    #[DataProvider('guestChannels')]
    public function test_enabled_zero_stock_products_create_pending_orders_without_fabricating_stock(string $channel): void
    {
        $product = $this->product(['allow_backorder' => true]);

        if ($channel === 'checkout') {
            $this->post('/cart/add', ['product_id' => $product->id, 'qty' => 2])->assertSessionHasNoErrors();
            $this->post('/checkout', $this->customer())->assertRedirect()->assertSessionHasNoErrors();
        } elseif ($channel === 'quick') {
            $this->postJson('/quick-order', [...$this->customer(), 'product_id' => $product->id, 'qty' => 2])
                ->assertOk()->assertJsonPath('success', true);
        } else {
            LandingPage::create(['title' => 'Clock offer', 'slug' => 'clock-offer', 'design' => 'product', 'product_id' => $product->id, 'is_active' => true]);
            $this->postJson('/lp/clock-offer/order', [...$this->customer(), 'qty' => 2])
                ->assertOk()->assertJsonPath('ok', true);
        }

        $order = Order::firstOrFail();
        $item = $order->items()->sole();
        $this->assertSame('pending', $order->status);
        $this->assertTrue($order->awaiting_sourcing);
        $this->assertTrue($item->sourcing_required);
        $this->assertSame(0, $item->reserved_stock_quantity);
        $this->assertSame(2, $item->sourcing_outstanding);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(0, $product->fresh()->sourced_stock_quantity);

        $this->get(route('order.confirmation', ['order' => $order, 'token' => $order->confirmation_token]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('order.awaiting_sourcing', true));
    }

    public static function guestChannels(): array
    {
        return [['checkout'], ['quick'], ['landing']];
    }

    public function test_cart_quantity_updates_and_buy_now_accept_enabled_products_without_a_stock_limit(): void
    {
        $product = $this->product(['allow_backorder' => true]);
        $this->postJson('/cart/add', ['product_id' => $product->id, 'qty' => 2])->assertOk();
        $this->postJson('/cart/update', ['key' => $product->id.'|', 'qty' => 4])->assertOk()->assertJsonPath('cart.count', 4);
        $this->postJson('/cart/buy-now', ['product_id' => $product->id, 'qty' => 3])
            ->assertOk()->assertJsonPath('cart.count', 3);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_disabling_the_switch_rejects_a_previously_saved_cart_at_checkout(): void
    {
        $product = $this->product(['allow_backorder' => true]);
        $this->post('/cart/add', ['product_id' => $product->id])->assertSessionHasNoErrors();
        $product->update(['allow_backorder' => false]);

        $this->post('/checkout', $this->customer())->assertSessionHasErrors('cart');

        $this->postJson('/cart/update', ['key' => $product->id.'|', 'qty' => 2])->assertUnprocessable();
        $this->postJson('/cart/buy-now', ['product_id' => $product->id])->assertUnprocessable();
        $this->postJson('/quick-order', [...$this->customer(), 'product_id' => $product->id, 'qty' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_mixed_cart_reserves_available_units_and_waits_only_for_the_shortage(): void
    {
        $clock = $this->product(['allow_backorder' => true, 'stock_quantity' => 2]);
        $mouse = $this->product(['name' => 'Warehouse mouse', 'stock_quantity' => 3]);
        $this->post('/cart/add', ['product_id' => $clock->id, 'qty' => 3])->assertSessionHasNoErrors();
        $this->post('/cart/add', ['product_id' => $mouse->id, 'qty' => 1])->assertSessionHasNoErrors();

        $this->post('/checkout', $this->customer())->assertRedirect()->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $clockItem = $order->items()->where('product_id', $clock->id)->firstOrFail();
        $mouseItem = $order->items()->where('product_id', $mouse->id)->firstOrFail();
        $this->assertTrue($order->awaiting_sourcing);
        $this->assertSame(2, $clockItem->reserved_stock_quantity);
        $this->assertSame(1, $clockItem->sourcing_outstanding);
        $this->assertSame(0, $mouseItem->sourcing_outstanding);
        $this->assertSame(0, $clock->fresh()->stock_quantity);
        $this->assertSame(2, $mouse->fresh()->stock_quantity);
    }

    #[DataProvider('adminChannels')]
    public function test_admin_order_entry_keeps_unreceived_products_pending(string $channel): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product(['allow_backorder' => true]);

        if ($channel === 'manual') {
            $this->post('/admin/orders', [...$this->customer(), 'items' => [['product_id' => $product->id, 'qty' => 2]]])
                ->assertRedirect()->assertSessionHasNoErrors();
        } elseif ($channel === 'add_items') {
            $order = Order::factory()->create();
            $this->post('/admin/orders/'.$order->id.'/items', ['items' => [['product_id' => $product->id, 'quantity' => 2]]])
                ->assertRedirect()->assertSessionHasNoErrors();
        } else {
            $abandoned = AbandonedCheckout::create([
                'session_id' => 'source-on-order', 'customer_name' => 'Samir Rahman', 'customer_phone' => '01711223344',
                'shipping_address' => 'House 25, Road 4, Mirpur Dhaka', 'cart_total' => 600,
                'cart_items' => [['product_id' => $product->id, 'qty' => 2]],
            ]);
            $this->post('/admin/abandoned-checkouts/'.$abandoned->id.'/create-order')->assertRedirect()->assertSessionHasNoErrors();
            $this->assertTrue($abandoned->fresh()->is_recovered);
        }

        $order = Order::firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertTrue($order->awaiting_sourcing);
        $this->assertSame(2, $order->items()->sole()->sourcing_outstanding);
        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public static function adminChannels(): array
    {
        return [['manual'], ['add_items'], ['recovered']];
    }

    #[DataProvider('verificationChannels')]
    public function test_payment_verification_does_not_confirm_an_order_awaiting_sourcing(string $channel): void
    {
        $order = $this->sourcingOrder();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        if ($channel === 'single') {
            $this->post('/admin/orders/'.$order->id.'/verify')->assertRedirect()->assertSessionHasNoErrors();
        } else {
            $this->post('/admin/orders/bulk', ['ids' => [$order->id], 'bulk_action' => 'verify'])
                ->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertSame('verified', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertTrue($order->fresh()->awaiting_sourcing);
    }

    public static function verificationChannels(): array
    {
        return [['single'], ['bulk']];
    }

    #[DataProvider('fulfilmentStatuses')]
    public function test_order_cannot_advance_before_its_sourcing_shortage_is_allocated(string $status): void
    {
        $order = $this->sourcingOrder();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->patchJson('/admin/orders/'.$order->id, ['status' => $status])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertTrue($order->fresh()->awaiting_sourcing);
    }

    public static function fulfilmentStatuses(): array
    {
        return [['confirmed'], ['processing'], ['shipped'], ['delivered']];
    }

    public function test_awaiting_sourcing_queue_excludes_normal_and_cancelled_orders(): void
    {
        $order = $this->sourcingOrder();
        $cancelled = $this->sourcingOrder();
        $cancelled->update(['status' => 'cancelled']);
        Order::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin/orders?status=awaiting_sourcing')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('status', 'awaiting_sourcing')->has('orders.data', 1)
            ->where('orders.data.0.id', $order->id)->where('orders.data.0.awaiting_sourcing', true)
            ->where('counts.awaiting_sourcing', 1)->where('counts.all', 3));
    }

    private function sourcingOrder(): Order
    {
        $product = $this->product(['allow_backorder' => true]);
        $this->postJson('/quick-order', [...$this->customer(), 'product_id' => $product->id, 'qty' => 1])
            ->assertOk()->assertJsonPath('success', true);

        return Order::latest('id')->firstOrFail();
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'source-on-order'], ['name' => 'Clocks', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id, 'name' => 'Digital LCD clock', 'regular_price' => 300,
            'stock_quantity' => 0, 'is_published' => true, ...$attributes,
        ]);
    }

    private function customer(): array
    {
        return [
            'customer_name' => 'Samir Rahman', 'customer_phone' => '01711223344',
            'shipping_address' => 'House 25, Road 4, Mirpur Dhaka', 'city' => 'Dhaka',
            'shipping_zone' => 'inside_dhaka', 'payment_method' => 'cod',
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SourceOnOrderPresentationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_tracking_shows_sourcing_status_without_internal_order_or_inventory_fields(): void
    {
        $order = $this->sourcingOrder();

        $this->post('/track', ['order_number' => $order->order_number, 'phone' => '+8801711223344'])
            ->assertInertia(fn (Assert $page) => $page->where('order.awaiting_sourcing', true)
                ->where('order.items.0.sourcing_required', true)->where('order.items.0.sourcing_outstanding', 2)
                ->missing('order.confirmation_token')->missing('order.internal_note')->missing('order.ip_address')
                ->missing('order.items.0.product.stock_quantity')->missing('order.items.0.product.sourced_stock_quantity'));

        $this->post('/track', ['order_number' => $order->order_number, 'phone' => '01811223344'])
            ->assertSessionHasErrors('order_number');
    }

    public function test_account_list_and_detail_show_sourcing_status_only_to_the_order_owner(): void
    {
        $customer = User::factory()->create();
        $order = $this->sourcingOrder(['user_id' => $customer->id]);
        $otherOrder = $this->sourcingOrder(['customer_phone' => '01811223344']);
        $this->actingAs($customer);

        $this->get('/account')->assertInertia(fn (Assert $page) => $page->has('orders', 1)
            ->where('orders.0.awaiting_sourcing', true)->where('orders.0.items.0.sourcing_outstanding', 2)
            ->missing('orders.0.confirmation_token')->missing('orders.0.internal_note'));
        $this->get('/account/orders/'.$order->order_number)->assertInertia(fn (Assert $page) => $page
            ->where('order.awaiting_sourcing', true)->where('order.items.0.sourcing_required', true)
            ->missing('order.device_hash')->missing('order.items.0.product.stock_quantity'));
        $this->get('/account/orders/'.$otherOrder->order_number)->assertForbidden();
    }

    #[DataProvider('landingDesigns')]
    public function test_landing_designs_explain_enabled_sourcing_without_changing_normal_campaigns(string $design): void
    {
        $product = $this->product(['allow_backorder' => true]);
        $landing = LandingPage::create(['title' => 'Clock offer', 'slug' => 'clock-'.$design, 'design' => $design,
            'product_id' => $product->id, 'is_active' => true]);

        $this->get('/lp/'.$landing->slug)->assertSeeText('Available to order')
            ->assertSeeText('We will confirm availability and delivery timing before dispatch.');

        $product->update(['stock_quantity' => 3]);
        $this->get('/lp/'.$landing->slug)->assertSeeText('In Stock')->assertDontSeeText('3 in stock');

        $product->update(['allow_backorder' => false, 'stock_quantity' => 0]);
        $this->get('/lp/'.$landing->slug)->assertDontSeeText('Available to order')
            ->assertDontSeeText('We source unavailable items after receiving your order.');
    }

    public static function landingDesigns(): array
    {
        return [['campaign'], ['product'], ['nuraya'], ['chilora']];
    }

    public function test_email_acknowledgements_do_not_confirm_orders_that_still_need_sourcing(): void
    {
        $order = $this->sourcingOrder();

        $placed = view('emails.order_placed', ['order' => $order, 'siteName' => 'Test Store'])->render();
        $verified = view('emails.order_verified', ['order' => $order, 'siteName' => 'Test Store'])->render();
        $admin = view('emails.admin_new_order', ['order' => $order, 'siteName' => 'Test Store'])->render();

        $this->assertStringContainsString('Awaiting sourcing', $placed);
        $this->assertStringContainsString('Payment verified — Awaiting sourcing', $verified);
        $this->assertStringNotContainsString('Order Confirmed', $verified);
        $this->assertStringNotContainsString('We are now preparing your order.', $verified);
        $this->assertStringContainsString('Receive and allocate the required items', $admin);

        $order->items->first()->update(['reserved_stock_quantity' => 2]);
        $order->refresh();
        $verified = view('emails.order_verified', ['order' => $order, 'siteName' => 'Test Store'])->render();
        $this->assertStringContainsString('Order Confirmed', $verified);
        $this->assertStringNotContainsString('Awaiting sourcing', $verified);
    }

    public function test_dashboard_and_analytics_count_received_stock_without_treating_the_switch_as_inventory(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->product(['source_type' => 'dropshipping', 'stock_quantity' => 8, 'sourced_stock_quantity' => 2]);
        $this->product(['stock_quantity' => 4, 'sourced_stock_quantity' => 3]);
        $this->product(['stock_quantity' => 0, 'sourced_stock_quantity' => 1]);
        $this->product(['allow_backorder' => true, 'stock_quantity' => 0]);

        $this->get('/admin')->assertInertia(fn (Assert $page) => $page->where('stats.low_stock', 1));
        $this->get('/admin/analytics?range=today')->assertInertia(fn (Assert $page) => $page
            ->where('inventorySummary.out_of_stock', 1)->where('inventorySummary.low_stock', 1)
            ->where('inventorySummary.units_on_hand', 10)->where('inventorySummary.supplier_availability', 8));
    }

    private function sourcingOrder(array $attributes = []): Order
    {
        $product = $this->product(['allow_backorder' => true]);
        $order = Order::factory()->create(['customer_phone' => '01711223344', 'internal_note' => 'Private supplier detail', ...$attributes]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name,
            'unit_price' => 300, 'quantity' => 2, 'line_total' => 600,
            'sourcing_required' => true, 'reserved_stock_quantity' => 0]);

        return $order;
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'clocks'], ['name' => 'Clocks']);

        return Product::create(['category_id' => $category->id, 'name' => 'Clock',
            'regular_price' => 300, 'stock_quantity' => 0, 'is_published' => true, ...$attributes]);
    }
}

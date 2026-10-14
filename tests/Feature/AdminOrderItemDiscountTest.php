<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminInvoiceController;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class AdminOrderItemDiscountTest extends TestCase
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

    private function order(): Order
    {
        $order = Order::factory()->create(['discount_amount' => 100, 'tax' => 90, 'total' => 1050]);
        $order->items()->create(['product_name' => 'Discounted Product', 'unit_price' => 200, 'quantity' => 2, 'line_total' => 400]);
        $order->items()->create(['product_name' => 'Other Product', 'unit_price' => 600, 'quantity' => 1, 'line_total' => 600]);

        return $order;
    }

    public function test_item_discount_can_be_applied_changed_and_removed_without_affecting_other_items_or_orders(): void
    {
        $order = $this->order();
        $item = $order->items()->first();
        $otherItem = $order->items()->latest('id')->first();
        $otherOrder = Order::factory()->create();

        foreach ([[25, '350.00', '950.00', '85.00', '995.00'], [50, '300.00', '900.00', '80.00', '940.00'], [0, '400.00', '1000.00', '90.00', '1050.00']] as [$discount, $lineTotal, $subtotal, $tax, $total]) {
            $this->from("/admin/orders/{$order->id}")
                ->patch("/admin/orders/{$order->id}/items/{$item->id}/discount", ['unit_discount' => $discount, 'unit_price' => 1, 'total' => 1])
                ->assertRedirect("/admin/orders/{$order->id}")->assertSessionHasNoErrors();
            $order->refresh();
            $item->refresh();
            $this->assertEquals($discount, $item->unit_discount);
            $this->assertSame('200.00', $item->unit_price);
            $this->assertSame(2, $item->quantity);
            $this->assertSame($lineTotal, $item->line_total);
            $this->assertSame($subtotal, $order->subtotal);
            $this->assertSame($tax, $order->tax);
            $this->assertSame($total, $order->total);
            $this->assertSame('100.00', $order->discount_amount);
            $this->assertSame('60.00', $order->shipping_charge);
            $this->assertSame('600.00', $otherItem->fresh()->line_total);
            $this->assertSame('1060.00', $otherOrder->fresh()->total);
        }
    }

    public function test_full_discount_stays_zero_on_admin_page_and_all_invoice_renderers(): void
    {
        $order = Order::factory()->create(['subtotal' => 400, 'tax' => 40, 'total' => 500]);
        $item = $order->items()->create(['product_name' => 'Free Product', 'unit_price' => 200, 'quantity' => 2, 'line_total' => 400]);
        $this->patch("/admin/orders/{$order->id}/items/{$item->id}/discount", ['unit_discount' => 200])
            ->assertSessionHasNoErrors();
        $this->assertSame('0.00', $item->fresh()->line_total);
        $this->assertSame('0.00', $order->fresh()->subtotal);
        $this->assertSame('0.00', $order->fresh()->tax);
        $this->assertSame('60.00', $order->fresh()->total);
        $this->get("/admin/orders/{$order->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Orders/Show')
                ->where('order.items.0.unit_discount', '200.00')->where('order.items.0.line_total', 0));

        $response = $this->get("/admin/orders/{$order->id}/invoice")->assertOk()->assertSee('Discount per unit: -৳200.00', false);
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame('৳0.00', trim($xpath->evaluate("string(//table[@class='items-table']/tbody/tr[1]/td[last()])")));
        $this->assertSame('৳0.00', trim($xpath->evaluate("string(//table[@class='summary-table']/tr[1]/td[last()])")));

        $controller = new AdminInvoiceController;
        $store = (new ReflectionMethod($controller, 'getStoreDetails'))->invoke($controller);
        $order = $order->fresh()->load('items.product.images');
        $fallbackHtml = (new ReflectionMethod($controller, 'fallbackInvoicePdfHtml'))->invoke($controller, $order, $store);
        $this->assertStringContainsString('Discount per unit: -৳200.00', $fallbackHtml);
        $this->assertStringContainsString('<td class="right">৳0.00</td>', $fallbackHtml);
        $pdf = (new ReflectionMethod($controller, 'nativeInvoicePdf'))->invoke($controller, $order, $store, 'color');
        $this->assertStringContainsString('Discount/unit: -BDT 200.00', $pdf);
    }

    #[DataProvider('invalidDiscounts')]
    public function test_invalid_item_discounts_leave_saved_totals_unchanged(mixed $discount): void
    {
        $order = $this->order();
        $item = $order->items()->first();
        $this->patchJson("/admin/orders/{$order->id}/items/{$item->id}/discount", ['unit_discount' => $discount])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_discount');
        $this->assertSame('0.00', $item->fresh()->unit_discount);
        $this->assertSame('400.00', $item->fresh()->line_total);
        $this->assertSame('1050.00', $order->fresh()->total);
    }

    public static function invalidDiscounts(): array
    {
        return [[-1], [200.01], [10.001], ['invalid'], [''], [null]];
    }

    public function test_an_item_from_another_order_cannot_be_discounted(): void
    {
        $order = $this->order();
        $otherOrder = $this->order();
        $item = $otherOrder->items()->first();
        $this->patch("/admin/orders/{$order->id}/items/{$item->id}/discount", ['unit_discount' => 50])->assertNotFound();
        $this->assertSame('0.00', $item->fresh()->unit_discount);
        $this->assertSame('1050.00', $order->fresh()->total);
        $this->assertSame('1050.00', $otherOrder->fresh()->total);
    }

    public function test_combined_discounts_cannot_exceed_product_subtotal(): void
    {
        $order = $this->order();
        $order->update(['discount_amount' => 800, 'tax' => 20, 'total' => 280]);
        $item = $order->items()->first();
        $this->patchJson("/admin/orders/{$order->id}/items/{$item->id}/discount", ['unit_discount' => 150])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_discount');
        $this->assertSame('0.00', $item->fresh()->unit_discount);
        $this->assertSame('1000.00', $order->fresh()->subtotal);
        $this->assertSame('280.00', $order->fresh()->total);
    }

    public function test_order_discount_after_item_discount_uses_net_product_subtotal(): void
    {
        $order = $this->order();
        $item = $order->items()->first();
        $this->patch("/admin/orders/{$order->id}/items/{$item->id}/discount", ['unit_discount' => 50])->assertSessionHasNoErrors();
        $this->patch("/admin/orders/{$order->id}", ['discount_amount' => 200])->assertSessionHasNoErrors();
        $this->assertSame('900.00', $order->fresh()->subtotal);
        $this->assertSame('70.00', $order->fresh()->tax);
        $this->assertSame('830.00', $order->fresh()->total);
        $this->assertSame('300.00', $item->fresh()->line_total);
    }

    public function test_customer_cannot_change_product_discount(): void
    {
        $order = $this->order();
        $item = $order->items()->first();
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->patch("/admin/orders/{$order->id}/items/{$item->id}/discount", ['unit_discount' => 50])
            ->assertRedirect(route('admin.login'));
        $this->assertSame('0.00', $item->fresh()->unit_discount);
    }

    public function test_product_catalog_price_and_stock_stay_unchanged_and_later_additions_keep_item_discount(): void
    {
        $category = Category::create(['name' => 'Test Category', 'slug' => 'item-discount-test', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Shared Product', 'regular_price' => 300, 'stock_quantity' => 5, 'is_published' => true]);
        $order = $this->order();
        $item = $order->items()->first();
        $item->update(['product_id' => $product->id]);

        $this->patch("/admin/orders/{$order->id}/items/{$item->id}/discount", ['unit_discount' => 50])->assertSessionHasNoErrors();
        $this->assertSame('300.00', $product->fresh()->regular_price);
        $this->assertSame(5, $product->fresh()->stock_quantity);

        $this->post("/admin/orders/{$order->id}/items", ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertSessionHasNoErrors();
        $this->assertSame('50.00', $item->fresh()->unit_discount);
        $this->assertSame('300.00', $item->fresh()->line_total);
        $this->assertSame('1200.00', $order->fresh()->subtotal);
        $this->assertSame('1270.00', $order->fresh()->total);
        $this->assertSame('300.00', $product->fresh()->regular_price);
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }
}

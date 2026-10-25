<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use App\Services\Dropshipping\PriceStockSyncService;
use App\Services\Dropshipping\PricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'inventory-test'], ['name' => 'Inventory Test', 'is_active' => true]);
        return Product::create(array_merge(['category_id' => $category->id, 'name' => 'Inventory Product', 'regular_price' => 300, 'stock_quantity' => 10, 'is_published' => true], $attributes));
    }

    private function supplierSource(Product $product): DropshipSupplierProduct
    {
        $supplier = DropshipSupplier::create(['key' => 'test-supplier', 'name' => 'Test Supplier', 'driver_key' => 'fake', 'base_url' => 'https://supplier.example', 'is_active' => true]);
        $product->update(['supplier_id' => $supplier->id, 'source_type' => 'dropshipping']);
        $source = DropshipSupplierProduct::create(['supplier_id' => $supplier->id, 'supplier_product_id' => 'stock-1', 'name' => $product->name, 'currency' => 'BDT', 'cost_price' => 100, 'max_price' => 300, 'stock_qty' => 20, 'is_available' => true, 'raw_payload' => [], 'fetched_at' => now()]);
        DropshipProductLink::create(['supplier_product_row_id' => $source->id, 'product_id' => $product->id, 'sync_status' => 'active', 'product_created_by_integration' => true, 'field_sync_rules' => ['stock' => true, 'price' => false]]);
        return $source;
    }

    private function adjustment(array $products, array $overrides = []): array
    {
        return array_merge(['items' => array_map(fn ($product) => ['product_id' => $product->id, 'expected_stock' => $product->stock_quantity], $products), 'operation' => 'add', 'quantity' => 3, 'reason' => 'Restock PO-100', 'protect_supplier_stock' => true], $overrides);
    }

    public function test_stock_adjustments_record_reason_actor_and_before_after_quantities(): void
    {
        $product = $this->product();
        $this->from('/admin/inventory')->post('/admin/inventory/adjust', $this->adjustment([$product]))
            ->assertRedirect('/admin/inventory')->assertSessionHasNoErrors();
        $this->assertSame(13, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_adjustments', ['product_id' => $product->id, 'stock_before' => 10, 'stock_after' => 13, 'change' => 3, 'reason' => 'Restock PO-100', 'user_id' => auth()->id()]);
        $this->getJson("/admin/inventory/{$product->id}/history")->assertOk()->assertJsonPath('data.0.before', 10)->assertJsonPath('data.0.after', 13)->assertJsonPath('data.0.actor', auth()->user()->name);
    }

    public function test_bulk_adjustment_is_atomic_when_one_product_has_stale_stock(): void
    {
        $first = $this->product(); $second = $this->product(['name' => 'Second Product']);
        $payload = $this->adjustment([$first, $second]);
        $second->update(['stock_quantity' => 9]);
        $this->postJson('/admin/inventory/adjust', $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(9, $second->fresh()->stock_quantity);
        $this->assertSame(0, InventoryAdjustment::count());
    }

    public function test_removing_more_stock_than_available_cannot_partially_update_a_batch(): void
    {
        $first = $this->product(); $second = $this->product(['stock_quantity' => 1]);
        $this->postJson('/admin/inventory/adjust', $this->adjustment([$first, $second], ['operation' => 'remove']))
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(0, InventoryAdjustment::count());
    }

    public function test_setting_stock_to_zero_and_removing_units_work(): void
    {
        $product = $this->product();
        $this->post('/admin/inventory/adjust', $this->adjustment([$product], ['operation' => 'remove', 'quantity' => 2]))->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame(8, $product->stock_quantity);
        $this->post('/admin/inventory/adjust', $this->adjustment([$product], ['operation' => 'set', 'quantity' => 0]))->assertSessionHasNoErrors();
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(2, InventoryAdjustment::count());
    }

    public function test_manual_stock_can_be_protected_then_supplier_sync_resumed(): void
    {
        $product = $this->product(); $source = $this->supplierSource($product);
        $this->post('/admin/inventory/adjust', $this->adjustment([$product]))->assertSessionHasNoErrors();
        $this->assertFalse($source->productLink->fresh()->field_sync_rules['stock']);
        app(PriceStockSyncService::class)->sync($source);
        $this->assertSame(13, $product->fresh()->stock_quantity);
        $this->post("/admin/inventory/{$product->id}/resume-sync")->assertSessionHasNoErrors();
        $this->assertSame(13, $product->fresh()->stock_quantity);
        $this->assertTrue($source->productLink->fresh()->field_sync_rules['stock']);
        app(PriceStockSyncService::class)->sync($source);
        $this->assertSame(20, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_adjustments', ['operation' => 'sync_resume', 'stock_before' => 13, 'stock_after' => 13]);
    }

    public function test_adjustment_can_leave_supplier_stock_sync_enabled(): void
    {
        $product = $this->product(); $source = $this->supplierSource($product);
        $this->post('/admin/inventory/adjust', $this->adjustment([$product], ['protect_supplier_stock' => false]))->assertSessionHasNoErrors();
        $this->assertTrue($source->productLink->fresh()->field_sync_rules['stock']);
        app(PriceStockSyncService::class)->sync($source);
        $this->assertSame(20, $product->fresh()->stock_quantity);
    }

    public function test_inventory_uses_customer_price_variant_count_and_resolved_images(): void
    {
        $product = $this->product(['sku' => 'INV-1', 'sale_price' => 0]);
        $product->variants()->create(['type' => 'Color', 'value' => 'Blue', 'stock' => 10]);
        $product->images()->create(['path' => 'products/inventory.jpg', 'position' => 0, 'is_primary' => true]);
        $this->get('/admin/inventory?type=variable')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.has_variants', true)->where('products.data.0.variants_count', 1)
            ->where('products.data.0.price', '0.00')->where('products.data.0.image_url', asset('storage/products/inventory.jpg')));
    }

    public function test_older_import_uses_its_last_pricing_snapshot_without_changing_saved_prices(): void
    {
        $product = $this->product(['regular_price' => 990, 'sale_price' => 891]);
        $source = $this->supplierSource($product);
        $rules = ['minimum_markup' => ['type' => 'percent', 'value' => 10], 'selling_markup' => ['type' => 'percent', 'value' => 25]];
        $source->productLink->update(['pricing_snapshot' => app(PricingEngine::class)->calculate(100, 300, $rules)->toArray()]);
        $source->update(['cost_price' => 200, 'max_price' => 500]);
        $this->get('/admin/inventory')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.selling_price', '125.00')->where('products.data.0.minimum_price', '110.00')
            ->where('products.data.0.maximum_price', '300.00')->where('products.data.0.price_details_source', 'supplier_snapshot')
            ->where('products.data.0.regular_price', '990.00')->where('products.data.0.sale_price', '891.00'));
        $this->assertNull($product->fresh()->selling_price);
        $this->assertSame('891.00', $product->fresh()->sale_price);
    }

    public function test_older_import_without_snapshot_uses_supplier_rules_in_inventory_and_csv(): void
    {
        $product = $this->product(['sku' => 'OLD-IMPORT', 'regular_price' => 990, 'sale_price' => 891]);
        $source = $this->supplierSource($product);
        $source->update(['cost_price' => 590, 'max_price' => 990]);
        $source->supplier->update(['pricing_rules' => [
            'selling' => ['base' => 'supplier_cost', 'adjustment_type' => 'none', 'adjustment_value' => 0],
            'minimum' => ['base' => 'supplier_cost', 'adjustment_type' => 'plus_percent', 'adjustment_value' => 20, 'cap_enabled' => true, 'rounding' => ['mode' => 'down', 'increment' => 5]],
            'maximum' => ['base' => 'supplier_maximum', 'adjustment_type' => 'none', 'adjustment_value' => 0, 'cap_enabled' => true],
            'regular_price_source' => 'maximum', 'discount' => ['type' => 'percent', 'value' => 10, 'protect_minimum' => true],
        ]]);
        $this->get('/admin/inventory')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.selling_price', '705.00')->where('products.data.0.minimum_price', '705.00')
            ->where('products.data.0.maximum_price', '990.00')->where('products.data.0.price_details_source', 'supplier_rules'));
        $csv = $this->get('/admin/inventory/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('891.00,990.00,705.00,705.00,990.00', $csv);
        $this->assertNull($product->fresh()->minimum_price);
    }

    public function test_inventory_preserves_saved_prices_including_zero_with_supplier_fallback(): void
    {
        $product = $this->product(['selling_price' => 0, 'minimum_price' => 0, 'maximum_price' => null]);
        $this->supplierSource($product);
        $this->get('/admin/inventory')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.selling_price', '0.00')->where('products.data.0.minimum_price', '0.00')
            ->where('products.data.0.maximum_price', '300.00'));
    }

    public function test_inventory_does_not_invent_supplier_limits_for_manual_or_legacy_overrides(): void
    {
        $product = $this->product(['pricing_mode' => 'manual', 'selling_price' => 250]);
        $source = $this->supplierSource($product);
        $this->get('/admin/inventory')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.selling_price', '250.00')->where('products.data.0.minimum_price', null)
            ->where('products.data.0.maximum_price', null)->where('products.data.0.price_details_source', null));
        $product->update(['pricing_mode' => 'auto', 'selling_price' => null]);
        $source->productLink->update(['price_override' => ['result_regular_price' => 300]]);
        $this->get('/admin/inventory')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.selling_price', '300.00')->where('products.data.0.minimum_price', null)
            ->where('products.data.0.maximum_price', null)->where('products.data.0.price_details_source', null));
    }

    public function test_inventory_does_not_show_invalid_or_foreign_currency_price_estimates(): void
    {
        $product = $this->product();
        $source = $this->supplierSource($product);
        $source->update(['currency' => 'USD']);
        $this->get('/admin/inventory')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.minimum_price', null)->where('products.data.0.maximum_price', null));
        $source->update(['currency' => 'BDT', 'cost_price' => 500]);
        $this->get('/admin/inventory')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.minimum_price', null)->where('products.data.0.maximum_price', null));
    }

    public function test_overview_counts_match_category_and_visibility_before_stock_filter(): void
    {
        $first = $this->product(['stock_quantity' => 3]); $this->product(['stock_quantity' => 0]);
        $this->product(['stock_quantity' => 20, 'is_published' => false]);
        $this->get('/admin/inventory?category='.$first->category_id.'&visibility=published&stock_level=low')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 1)
                ->where('summary.total', 2)->where('summary.low_stock', 1)->where('summary.out_stock', 1)->where('matching_units', 3));
    }

    public function test_stock_source_filters_distinguish_local_supplier_and_manual_products(): void
    {
        $local = $this->product(['name' => 'Local Product']); $supplierProduct = $this->product(['name' => 'Supplier Product']);
        $source = $this->supplierSource($supplierProduct);
        $this->get('/admin/inventory?source=supplier')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 1)->where('products.data.0.id', $supplierProduct->id));
        $source->productLink->update(['field_sync_rules' => ['stock' => false]]);
        $this->get('/admin/inventory?source=manual')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 1)->where('products.data.0.stock_source', 'manual'));
        $this->get('/admin/inventory?source=local')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 1)->where('products.data.0.id', $local->id));
    }

    public function test_price_sort_uses_discounted_customer_price(): void
    {
        $cheap = $this->product(['name' => 'Discounted', 'regular_price' => 500, 'sale_price' => 100]);
        $this->product(['name' => 'Regular', 'regular_price' => 200]);
        $this->get('/admin/inventory?order=price_asc')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('products.data.0.id', $cheap->id));
    }

    public function test_filtered_export_is_utf8_and_neutralizes_spreadsheet_formulas(): void
    {
        $this->product(['name' => '=1+1', 'sku' => 'EXPORT-1', 'stock_quantity' => 2]);
        $this->product(['name' => 'Excluded', 'stock_quantity' => 0]);
        $response = $this->get('/admin/inventory/export?stock_level=low')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString('EXPORT-1', $csv);
        $this->assertStringNotContainsString('Excluded', $csv);
    }

    public function test_view_only_staff_cannot_mutate_stock(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create(['role' => 'inventory', 'permissions' => ['inventory.view']]))
            ->get('/admin/inventory')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_adjust', false));
        $this->postJson('/admin/inventory/adjust', $this->adjustment([$product]))->assertForbidden();
        $this->postJson("/admin/inventory/{$product->id}/resume-sync")->assertForbidden();
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    #[DataProvider('invalidAdjustments')]
    public function test_invalid_adjustment_input_is_rejected(array $values, string $field): void
    {
        $product = $this->product();
        $this->postJson('/admin/inventory/adjust', $this->adjustment([$product], $values))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(0, InventoryAdjustment::count());
    }

    public static function invalidAdjustments(): array
    {
        return [[['quantity' => 1.5], 'quantity'], [['quantity' => 0], 'quantity'], [['quantity' => -1], 'quantity'], [['quantity' => 4294967296], 'quantity'], [['reason' => ''], 'reason'], [['items' => []], 'items']];
    }
}

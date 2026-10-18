<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupplierInventoryTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function supplier(string $key): DropshipSupplier
    {
        return DropshipSupplier::create(['key' => $key, 'name' => ucfirst($key).' Supplier', 'driver_key' => 'manual', 'supplier_type' => 'local', 'is_active' => true]);
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'supplier-inventory'], ['name' => 'Supplier inventory', 'is_active' => true]);
        return Product::create([...['category_id' => $category->id, 'name' => 'Manual product', 'sku' => 'LOCAL-100', 'regular_price' => 100, 'stock_quantity' => 10, 'source_type' => 'manual'], ...$attributes]);
    }

    private function receive(Product $product, array $values = [])
    {
        return $this->post('/admin/inventory/adjust', [...[
            'items' => [['product_id' => $product->id, 'expected_stock' => $product->stock_quantity]],
            'operation' => 'add', 'quantity' => 5, 'reason' => 'Stock received',
            'movement_type' => 'stock_in', 'reference' => 'RECEIPT-2026-100', 'protect_supplier_stock' => true,
        ], ...$values]);
    }

    public function test_receipt_retains_supplier_after_primary_supplier_changes(): void
    {
        $first = $this->supplier('first'); $second = $this->supplier('second');
        $product = $this->product(['supplier_id' => $first->id]);
        $this->receive($product)->assertSessionHasNoErrors();
        $product->update(['supplier_id' => $second->id]);

        $this->assertSame(15, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_adjustments', ['product_id' => $product->id, 'supplier_id' => $first->id, 'movement_type' => 'stock_in', 'reference' => 'RECEIPT-2026-100', 'stock_scope' => 'local', 'user_id' => auth()->id(), 'stock_before' => 10, 'stock_after' => 15]);
        $this->getJson('/admin/inventory/'.$product->id.'/history')->assertOk()
            ->assertJsonPath('data.0.supplier_id', $first->id)->assertJsonPath('data.0.supplier', $first->name)
            ->assertJsonPath('data.0.reference', 'RECEIPT-2026-100')->assertJsonPath('data.0.stock_scope', 'local');
        $this->get('/admin/inventory/history?supplier='.$first->id.'&stock_scope=local&movement_type=stock_in&q=RECEIPT-2026')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Inventory/History')->has('movements.data', 1)->where('movements.data.0.supplier_id', $first->id));
        $this->get('/admin/inventory/history?supplier='.$second->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('movements.data', 0));
        $csv = $this->get('/admin/inventory/history/export?supplier='.$first->id)->assertOk()->streamedContent();
        $this->assertStringContainsString('RECEIPT-2026-100', $csv);
        $this->assertStringContainsString($first->name, $csv);
    }

    public function test_receipt_supplier_can_differ_from_primary_supplier_without_reassigning_product(): void
    {
        $primary = $this->supplier('primary'); $receipt = $this->supplier('receipt');
        $product = $this->product(['supplier_id' => $primary->id]);
        $this->receive($product, ['supplier_id' => $receipt->id])->assertSessionHasNoErrors();
        $this->assertSame($primary->id, $product->fresh()->supplier_id);
        $this->assertDatabaseHas('inventory_adjustments', ['product_id' => $product->id, 'supplier_id' => $receipt->id]);

        $this->receive($product->fresh(), ['supplier_id' => null])->assertSessionHasNoErrors();
        $this->assertNull(InventoryAdjustment::latest('id')->first()->supplier_id);
    }

    public function test_inactive_receipt_supplier_is_rejected_without_stock_change(): void
    {
        $supplier = $this->supplier('inactive'); $supplier->update(['is_active' => false]);
        $product = $this->product();
        $this->receive($product, ['supplier_id' => $supplier->id])->assertSessionHasErrors('supplier_id');
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_adjustments', 0);
    }

    #[DataProvider('movements')]
    public function test_movement_type_and_reference_are_recorded(string $movement, string $operation, int $quantity, int $after): void
    {
        $product = $this->product();
        $this->receive($product, ['movement_type' => $movement, 'operation' => $operation, 'quantity' => $quantity])->assertSessionHasNoErrors();
        $this->assertSame($after, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_adjustments', ['movement_type' => $movement, 'change' => $after - 10, 'reference' => 'RECEIPT-2026-100']);
    }

    public static function movements(): array
    {
        return [['stock_in', 'add', 4, 14], ['stock_out', 'remove', 2, 8], ['adjustment', 'set', 7, 7], ['return_in', 'add', 3, 13], ['damaged', 'remove', 1, 9]];
    }

    public function test_opening_stock_is_only_recorded_once_and_product_edit_tracks_actual_changes(): void
    {
        $supplier = $this->supplier('opening');
        $product = $this->product(['supplier_id' => $supplier->id]);
        $stock = app(InventoryStockService::class);
        $stock->recordOpening(auth()->user(), $product, 'INITIAL-1');
        $stock->recordOpening(auth()->user(), $product, 'INITIAL-1');
        $stock->recordProductEdit(auth()->user(), $product, 10);
        $this->assertDatabaseCount('inventory_adjustments', 1);
        $this->assertDatabaseHas('inventory_adjustments', ['supplier_id' => $supplier->id, 'movement_type' => 'opening', 'stock_before' => 0, 'stock_after' => 10]);
        $product->update(['stock_quantity' => 8]);
        $stock->recordProductEdit(auth()->user(), $product, 10);
        $this->assertDatabaseHas('inventory_adjustments', ['movement_type' => 'adjustment', 'stock_before' => 10, 'stock_after' => 8]);
        $this->receive($product, ['movement_type' => 'opening'])->assertSessionHasErrors('movement_type');
    }

    public function test_supplier_availability_is_excluded_from_local_totals_even_when_sync_is_paused(): void
    {
        $supplier = $this->supplier('integrated');
        $this->product(['supplier_id' => $supplier->id, 'stock_quantity' => 10]);
        $dropship = $this->product(['supplier_id' => $supplier->id, 'source_type' => 'dropshipping', 'sku' => 'EXTERNAL-1', 'stock_quantity' => 80]);
        $external = DropshipSupplierProduct::create(['supplier_id' => $supplier->id, 'supplier_product_id' => 'remote-200', 'name' => 'External', 'currency' => 'BDT', 'raw_payload' => [], 'stock_qty' => 80, 'is_available' => true, 'fetched_at' => now()]);
        DropshipProductLink::create(['supplier_product_row_id' => $external->id, 'product_id' => $dropship->id, 'sync_status' => 'active', 'field_sync_rules' => ['stock' => false]]);

        app(InventoryStockService::class)->recordOpening(auth()->user(), $dropship);
        $this->assertDatabaseCount('inventory_adjustments', 0);
        $this->get('/admin/inventory?supplier='.$supplier->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.units', 10)->where('summary.supplier_units', 80)->where('matching_units', 10)->where('matching_supplier_units', 80)->has('products.data', 2));
        $this->get('/admin/inventory?supplier='.$supplier->id.'&product_source=dropshipping')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)->where('products.data.0.local_stock_quantity', 0)->where('products.data.0.supplier_stock_quantity', 80)->where('summary.units', 0));
        $this->get('/admin/inventory?supplier='.$supplier->id.'&product_source=manual')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)->where('products.data.0.supplier', $supplier->name)->where('products.data.0.stock_type', 'local'));
        $this->get('/admin/analytics?range=today')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('inventorySummary.units_on_hand', 10)->where('inventorySummary.supplier_availability', 80));
        $csv = $this->get('/admin/inventory/export?supplier='.$supplier->id.'&product_source=dropshipping')->assertOk()->streamedContent();
        $this->assertStringContainsString('Supplier-managed availability', $csv);
        $this->assertStringContainsString('EXTERNAL-1', $csv);
        $this->assertStringNotContainsString('LOCAL-100', $csv);
    }

    public function test_non_owning_external_mapping_does_not_convert_manual_warehouse_stock(): void
    {
        $supplier = $this->supplier('non-owner');
        $product = $this->product();
        $external = DropshipSupplierProduct::create(['supplier_id' => $supplier->id, 'supplier_product_id' => 'non-owning-100', 'name' => 'Candidate mapping', 'currency' => 'BDT', 'raw_payload' => [], 'fetched_at' => now()]);
        $link = DropshipProductLink::create(['supplier_product_row_id' => $external->id, 'product_id' => $product->id, 'product_created_by_integration' => false, 'sync_status' => 'active', 'field_sync_rules' => ['stock' => true]]);
        app(InventoryStockService::class)->recordOpening(auth()->user(), $product);
        $this->assertDatabaseHas('inventory_adjustments', ['product_id' => $product->id, 'supplier_id' => null, 'stock_scope' => 'local', 'movement_type' => 'opening']);
        $this->get('/admin/inventory?source=local')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)->where('summary.units', 10)->where('summary.supplier_units', 0)->where('products.data.0.supplier', null)->where('products.data.0.can_resume_sync', false));
        $this->get('/admin/inventory?supplier='.$supplier->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));
        $this->post('/admin/inventory/'.$product->id.'/resume-sync')->assertSessionHasErrors('stock_sync');
        $this->assertTrue($link->fresh()->field_sync_rules['stock']);
    }
}

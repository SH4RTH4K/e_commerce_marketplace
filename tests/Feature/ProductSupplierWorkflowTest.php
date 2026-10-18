<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductSupplierWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function supplier(array $overrides = []): DropshipSupplier
    {
        return DropshipSupplier::create(array_merge(['key' => 'supplier-'.DropshipSupplier::count(), 'name' => 'Local supplier', 'supplier_type' => 'local', 'is_active' => true], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        $category = Category::firstOrCreate(['slug' => 'supplier-products'], ['name' => 'Supplier products']);
        return array_merge(['name' => 'Manual product', 'category_id' => $category->id, 'source_type' => 'manual', 'stock_quantity' => 10, 'regular_price' => 200, 'sale_price' => 180, 'sku' => 'LOCAL-1'], $overrides);
    }

    public function test_manual_product_can_use_local_or_dropshipping_type_supplier_without_changing_source(): void
    {
        foreach (['local', 'dropshipping'] as $type) {
            $supplier = $this->supplier(['supplier_type' => $type]);
            $this->post('/admin/products', $this->payload(['supplier_id' => $supplier->id, 'purchase_price' => 90]))->assertSessionHasNoErrors();
            $product = Product::latest('id')->firstOrFail();
            $this->assertSame($supplier->id, $product->supplier_id);
            $this->assertSame('manual', $product->source_type);
            $this->assertSame('90.00', $product->purchase_price);
            $this->assertFalse($product->supplierLinks()->exists());
            $this->get('/admin/products/'.$product->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->where('product.supplier_id', $supplier->id)->where('product.source_type', 'manual')->where('assignment.locked', false));
            $this->assertDatabaseHas('inventory_adjustments', ['product_id' => $product->id, 'supplier_id' => $supplier->id, 'stock_before' => 0, 'stock_after' => 10]);
            $this->assertArrayNotHasKey('purchase_price', $product->toArray());
        }
    }

    public function test_own_inventory_and_zero_opening_stock_work(): void
    {
        $this->post('/admin/products', $this->payload(['supplier_id' => null, 'stock_quantity' => 0]))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertNull($product->supplier_id);
        $this->assertSame(0, InventoryAdjustment::count());
    }

    public function test_supplier_change_preserves_prices_stock_images_and_sku_and_historical_supplier(): void
    {
        $first = $this->supplier(); $second = $this->supplier();
        $this->post('/admin/products', $this->payload(['supplier_id' => $first->id]))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $product->images()->create(['path' => 'products/unchanged.jpg', 'is_primary' => true, 'position' => 0]);
        $before = $product->only(['stock_quantity', 'regular_price', 'sale_price', 'sku']);
        $this->patch('/admin/products/'.$product->id, ['name' => $product->name, 'category_id' => $product->category_id, 'supplier_id' => $second->id])->assertSessionHasNoErrors();
        $this->assertSame($second->id, $product->fresh()->supplier_id);
        $this->assertSame($before, $product->fresh()->only(array_keys($before)));
        $this->assertSame('products/unchanged.jpg', $product->images()->first()->path);
        $this->assertSame(1, InventoryAdjustment::count());
        $this->assertSame($first->id, InventoryAdjustment::first()->supplier_id);
        $this->assertDatabaseHas('supplier_audits', ['event' => 'product_supplier_changed', 'supplier_id' => $second->id]);
    }

    public function test_inactive_invalid_or_deleted_suppliers_cannot_be_newly_assigned(): void
    {
        $inactive = $this->supplier(['is_active' => false]);
        $this->postJson('/admin/products', $this->payload(['supplier_id' => $inactive->id]))->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
        $this->postJson('/admin/products', $this->payload(['supplier_id' => 999999]))->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
        $deleted = $this->supplier(); $deleted->delete();
        $this->postJson('/admin/products', $this->payload(['supplier_id' => $deleted->id]))->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
        $this->assertSame(0, Product::count());
    }

    public function test_existing_inactive_supplier_is_preserved_on_normal_product_edit(): void
    {
        $supplier = $this->supplier();
        $this->post('/admin/products', $this->payload(['supplier_id' => $supplier->id]))->assertSessionHasNoErrors();
        $product = Product::firstOrFail(); $supplier->update(['is_active' => false]);
        $this->patch('/admin/products/'.$product->id, $this->payload(['supplier_id' => $supplier->id, 'name' => 'Updated manual name']))->assertSessionHasNoErrors();
        $this->assertSame($supplier->id, $product->fresh()->supplier_id);
        $this->get('/admin/products/'.$product->id.'/edit')->assertOk();
    }

    public function test_supplier_and_source_filters_work_independently_together_and_with_pagination(): void
    {
        $supplier = $this->supplier(); $other = $this->supplier();
        for ($i = 0; $i < 17; $i++) Product::create($this->payload(['name' => 'Filtered '.$i, 'supplier_id' => $supplier->id]));
        Product::create($this->payload(['supplier_id' => $supplier->id, 'source_type' => 'dropshipping']));
        Product::create($this->payload(['supplier_id' => $other->id]));
        $this->get('/admin/products?supplier='.$supplier->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('products.total', 18));
        $url = '/admin/products?q=Filtered&supplier='.$supplier->id.'&source=manual&per_page=15';
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('products.total', 17)->has('products.data', 15)->where('filters.source', 'manual')->where('products.next_page_url', fn ($url) => str_contains($url, 'source=manual') && str_contains($url, 'supplier=')));
        $this->get($url.'&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 2));
    }

    public function test_imported_supplier_and_source_cannot_be_arbitrarily_reassigned(): void
    {
        $supplier = $this->supplier(['driver_key' => 'fake', 'supplier_type' => 'dropshipping']); $other = $this->supplier();
        $product = Product::create($this->payload(['supplier_id' => $supplier->id, 'source_type' => 'dropshipping']));
        $source = DropshipSupplierProduct::create(['supplier_id' => $supplier->id, 'supplier_product_id' => 'EXTERNAL-3002', 'name' => 'External', 'currency' => 'BDT', 'fetched_at' => now(), 'raw_payload' => []]);
        DropshipProductLink::create(['product_id' => $product->id, 'supplier_product_row_id' => $source->id, 'product_created_by_integration' => true, 'sync_status' => 'active']);
        $this->patchJson('/admin/products/'.$product->id, $this->payload(['supplier_id' => $other->id, 'source_type' => 'dropshipping']))->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
        $this->patchJson('/admin/products/'.$product->id, $this->payload(['supplier_id' => $supplier->id, 'source_type' => 'manual']))->assertUnprocessable();
        $this->assertSame($supplier->id, $product->fresh()->supplier_id);
        $this->get('/admin/products/'.$product->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->where('assignment.locked', true)->where('assignment.external_product_id', 'EXTERNAL-3002'));
    }

    public function test_normal_form_cannot_invent_a_dropshipping_source_or_allow_unauthorized_assignment(): void
    {
        $this->postJson('/admin/products', $this->payload(['source_type' => 'dropshipping']))->assertUnprocessable()->assertJsonValidationErrors('source_type');
        $viewer = User::factory()->create(['role' => 'inventory', 'permissions' => ['products.view']]);
        $this->actingAs($viewer)->postJson('/admin/products', $this->payload())->assertForbidden();
    }
}

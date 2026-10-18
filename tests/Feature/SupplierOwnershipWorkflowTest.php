<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\DropshipSyncRun;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\SupplierAudit;
use App\Models\User;
use App\Services\Dropshipping\CategoryMapper;
use App\Services\Dropshipping\PriceStockSyncService;
use App\Services\Dropshipping\ProductImportService;
use App\Services\Dropshipping\SupplierAssociationService;
use App\Services\Dropshipping\SupplierRegistry;
use App\Services\Dropshipping\Suppliers\ConfigurableRestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SupplierOwnershipWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dropshipping.enabled' => true]);
    }

    public function test_import_uses_stable_supplier_ownership_and_external_ids_without_local_inventory_movements(): void
    {
        [$supplier, $source] = $this->source();
        $supplier->update(['supplier_type' => 'wholesale']);
        $source->update(['supplier_product_id' => 'external-3002']);
        $first = app(ProductImportService::class)->import($source);
        $second = app(ProductImportService::class)->import($source);
        $this->assertSame('imported', $first->status);
        $this->assertSame('already_linked', $second->status);
        $this->assertSame($supplier->id, $first->product->supplier_id);
        $this->assertSame('dropshipping', $first->product->source_type);
        $this->assertSame($first->product->id, $second->product->id);
        $this->assertNotEquals('external-3002', $first->product->id);
        $this->assertSame(1, DropshipSupplier::count());
        $this->assertSame(0, InventoryAdjustment::count());
    }

    public function test_ownership_conflict_blocks_content_images_price_and_stock_before_any_write(): void
    {
        [, $source] = $this->source();
        $product = app(ProductImportService::class)->import($source)->product;
        $other = $this->supplier('Other');
        $product->update(['supplier_id' => $other->id, 'sku' => 'MERCHANT', 'description' => 'Merchant description', 'regular_price' => 777, 'stock_quantity' => 42]);
        $before = $product->fresh()->getAttributes();
        $source->update(['stock_qty' => 3, 'cost_price' => 10, 'raw_payload' => ['description' => 'Unexpected replacement', 'thumbnail_img' => 'https://supplier.example/new.jpg']]);
        $import = app(ProductImportService::class)->import($source);
        $sync = app(PriceStockSyncService::class)->sync($source);
        $this->assertSame('blocked', $import->status);
        $this->assertSame('supplier_ownership_conflict', $import->reason);
        $this->assertContains('supplier_ownership_conflict', $sync->warnings);
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertSame(0, $product->images()->count());
    }

    public function test_accidental_manual_product_mapping_does_not_mutate_local_inventory(): void
    {
        [$supplier, $source] = $this->source();
        $product = Product::create(['category_id' => Category::first()->id, 'name' => 'Manual inventory', 'source_type' => 'manual', 'supplier_id' => $supplier->id, 'regular_price' => 800, 'stock_quantity' => 29]);
        DropshipProductLink::create(['supplier_product_row_id' => $source->id, 'product_id' => $product->id, 'product_created_by_integration' => false, 'field_sync_rules' => ['price' => true, 'stock' => true]]);
        $this->assertSame('manual_product_protected', app(ProductImportService::class)->import($source)->reason);
        $this->assertContains('manual_product_protected', app(PriceStockSyncService::class)->sync($source)->warnings);
        $this->assertSame(29, $product->fresh()->stock_quantity);
        $this->assertSame('manual', $product->fresh()->source_type);
    }

    public function test_missing_or_inactive_connection_fails_safely_without_duplicate_products(): void
    {
        [$supplier, $source] = $this->source();
        $supplier->update(['driver_key' => 'manual']);
        $this->assertSame('missing_supplier_connection', app(ProductImportService::class)->import($source)->reason);
        $supplier->update(['driver_key' => 'fake', 'is_active' => false]);
        $this->assertSame('inactive_supplier', app(ProductImportService::class)->import($source)->reason);
        $this->assertSame(0, Product::count());
    }

    public function test_backfill_reuses_existing_records_and_reports_conflicts_without_reassigning_products(): void
    {
        [$supplier, $source] = $this->source();
        $legacy = $this->linked($source);
        [, $conflictingSource] = $this->source();
        $other = $this->supplier('Manual owner');
        $conflict = $this->linked($conflictingSource, $other->id);
        [, $manualSource] = $this->source();
        $manual = $this->linked($manualSource, null, false);
        $first = app(SupplierAssociationService::class)->backfill();
        $second = app(SupplierAssociationService::class)->backfill();
        $this->assertSame(1, $first['updated']);
        $this->assertSame(0, $second['updated']);
        $this->assertSame(1, $second['unchanged']);
        $this->assertSame($conflict->id, $second['conflicts'][0]['product_id']);
        $this->assertSame($supplier->id, $legacy->fresh()->supplier_id);
        $this->assertSame('dropshipping', $legacy->fresh()->source_type);
        $this->assertSame($other->id, $conflict->fresh()->supplier_id);
        $this->assertSame('manual', $conflict->fresh()->source_type);
        $this->assertNull($manual->fresh()->supplier_id);
        $this->assertSame('manual', $manual->fresh()->source_type);
        $this->assertSame(4, DropshipSupplier::count());
    }

    public function test_backfill_command_is_idempotent_and_does_not_require_audit_table(): void
    {
        [, $source] = $this->source();
        $legacy = $this->linked($source);
        $this->artisan('suppliers:backfill-products')->expectsOutput('Updated: 1; unchanged: 0; conflicts: 0')->assertSuccessful();
        $this->artisan('suppliers:backfill-products')->expectsOutput('Updated: 0; unchanged: 1; conflicts: 0')->assertSuccessful();
        $this->assertSame(1, DropshipSupplier::count());
        $this->assertSame(16, $legacy->fresh()->stock_quantity);
    }

    public function test_existing_business_supplier_can_be_linked_once_without_duplicates_or_type_conversion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = DropshipSupplier::create(['key' => 'existing-local', 'supplier_code' => 'SUP-LOCAL', 'name' => 'Wholesale vendor', 'supplier_type' => 'wholesale', 'driver_key' => 'manual', 'base_url' => null, 'is_active' => true]);
        app()->instance(SupplierRegistry::class, new SupplierRegistry([new ConfigurableRestClient('fake')]));
        $data = ['supplier_id' => $supplier->id, 'name' => 'Do not overwrite business name', 'driver_key' => 'fake', 'base_url' => 'https://supplier.example/api', 'api_key' => 'test-secret'];
        $this->actingAs($admin)->post('/admin/dropshipping/suppliers', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('wholesale', $supplier->fresh()->supplier_type);
        $this->assertSame('Wholesale vendor', $supplier->fresh()->name);
        $this->assertSame('fake', $supplier->fresh()->driver_key);
        $this->assertSame('test-secret', $supplier->fresh()->api_key);
        $this->assertSame($admin->id, $supplier->fresh()->updated_by);
        $this->assertSame($admin->name, SupplierAudit::where('event', 'integration_connected')->sole()->actor_name);
        $this->assertSame(1, DropshipSupplier::count());
        $this->actingAs($admin)->post('/admin/dropshipping/suppliers', $data)->assertSessionHasErrors('supplier_id');
        $this->assertSame(1, DropshipSupplier::count());
        $this->assertDatabaseHas('supplier_audits', ['supplier_id' => $supplier->id, 'event' => 'integration_connected']);
        $this->actingAs($admin)->get('/admin/dropshipping')->assertInertia(fn ($page) => $page->where('suppliers.0.id', $supplier->id));
    }

    public function test_controlled_relink_requires_confirmation_and_records_exact_external_mapping(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        [$oldSupplier, $source] = $this->source();
        $product = app(ProductImportService::class)->import($source)->product;
        [$newSupplier, $target] = $this->source();
        $data = ['target_supplier_id' => $newSupplier->id, 'external_product_id' => $target->supplier_product_id, 'reason' => 'Supplier has corrected its external product reference.'];
        $url = "/admin/dropshipping/supplier-products/{$source->id}/relink";
        $this->actingAs($admin)->post($url, $data)->assertSessionHasErrors('confirmed');
        $this->assertSame($oldSupplier->id, $product->fresh()->supplier_id);
        $this->actingAs($admin)->post($url, [...$data, 'confirmed' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($newSupplier->id, $product->fresh()->supplier_id);
        $this->assertSame($product->id, $target->productLink()->first()->product_id);
        $this->assertNull($source->productLink()->first()->product_id);
        $this->assertSame('disabled', $source->productLink()->first()->sync_status);
        $this->assertSame($target->supplier_product_id, SupplierAudit::where('event', 'product_relinked')->sole()->after['external_product_id']);
        $this->assertSame($target->id, DropshipSyncRun::sole()->filters['supplier_product_ids'][0]);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_relink_rejects_a_product_owned_by_another_external_mapping(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        [, $source] = $this->source();
        $first = app(ProductImportService::class)->import($source)->product;
        [$targetSupplier, $target] = $this->source();
        $second = app(ProductImportService::class)->import($target)->product;
        $this->actingAs($admin)->post("/admin/dropshipping/supplier-products/{$source->id}/relink", ['target_supplier_id' => $targetSupplier->id, 'external_product_id' => $target->supplier_product_id, 'reason' => 'Attempted reassignment needs a review.', 'confirmed' => '1'])->assertSessionHasErrors('external_product_id');
        $this->assertSame($first->id, $source->productLink()->first()->product_id);
        $this->assertSame($second->id, $target->productLink()->first()->product_id);
        $this->assertSame(0, SupplierAudit::where('event', 'product_relinked')->count());
    }

    public function test_existing_non_null_manual_ownership_is_not_converted_by_a_sync(): void
    {
        [$supplier, $source] = $this->source();
        $product = $this->linked($source, $supplier->id);
        $this->assertSame('manual_product_protected', app(ProductImportService::class)->import($source)->reason);
        $this->assertContains('manual_product_protected', app(PriceStockSyncService::class)->sync($source)->warnings);
        $this->assertSame('manual', $product->fresh()->source_type);
        $this->assertSame(16, $product->fresh()->stock_quantity);
    }

    public function test_backfill_reports_multiple_external_links_without_changing_the_product(): void
    {
        [, $first] = $this->source();
        $product = $this->linked($first);
        [, $second] = $this->source();
        DropshipProductLink::create(['supplier_product_row_id' => $second->id, 'product_id' => $product->id, 'product_created_by_integration' => true]);
        $report = app(SupplierAssociationService::class)->backfill();
        $this->assertSame(0, $report['updated']);
        $this->assertCount(1, $report['conflicts']);
        $this->assertCount(2, $report['conflicts'][0]['mapped_supplier_ids']);
        $this->assertNull($product->fresh()->supplier_id);
        $this->assertSame(16, $product->fresh()->stock_quantity);
    }

    public function test_paused_import_mapping_cannot_resume_through_an_ordinary_import_click(): void
    {
        [, $source] = $this->source();
        $product = app(ProductImportService::class)->import($source)->product;
        $source->productLink()->update(['sync_status' => 'disabled']);
        $this->assertSame('supplier_sync_disabled', app(ProductImportService::class)->import($source)->reason);
        $this->assertSame($product->id, $source->productLink()->first()->product_id);
        $this->assertSame(1, Product::count());
    }

    public function test_active_sync_must_finish_or_be_cancelled_before_relinking(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        [$supplier, $source] = $this->source();
        $product = app(ProductImportService::class)->import($source)->product;
        [$targetSupplier, $target] = $this->source();
        DropshipSyncRun::create(['supplier_id' => $supplier->id, 'type' => 'catalog', 'status' => 'running']);
        $this->actingAs($admin)->post("/admin/dropshipping/supplier-products/{$source->id}/relink", ['target_supplier_id' => $targetSupplier->id, 'external_product_id' => $target->supplier_product_id, 'reason' => 'Change external mapping after supplier correction.', 'confirmed' => '1'])->assertSessionHasErrors('product');
        $this->assertSame($supplier->id, $product->fresh()->supplier_id);
        $this->assertSame(0, SupplierAudit::where('event', 'product_relinked')->count());
    }

    public function test_connection_edit_preserves_business_details_and_blank_credentials(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$supplier] = $this->source();
        $supplier->update(['supplier_type' => 'wholesale', 'api_key' => 'original-api-key', 'secret_key' => 'original-secret']);
        app()->instance(SupplierRegistry::class, new SupplierRegistry([new ConfigurableRestClient('fake')]));
        $this->actingAs($admin)->patch("/admin/dropshipping/suppliers/{$supplier->id}", ['name' => 'Changed API display name', 'key' => 'attempted-key-change', 'driver_key' => 'fake', 'base_url' => 'https://supplier.example/api', 'api_key' => '', 'secret_key' => ''])->assertSessionHasNoErrors();
        $this->assertSame('Supplier', $supplier->fresh()->name);
        $this->assertSame('wholesale', $supplier->fresh()->supplier_type);
        $this->assertSame('original-api-key', $supplier->fresh()->api_key);
        $this->assertSame('original-secret', $supplier->fresh()->secret_key);
        $this->assertNotSame('attempted-key-change', $supplier->fresh()->key);
        $this->assertSame($admin->id, $supplier->fresh()->updated_by);
        $audit = SupplierAudit::where('event', 'integration_updated')->sole();
        $this->assertSame($admin->name, $audit->actor_name);
        $this->assertSame(['driver_key' => 'fake'], $audit->after);
        $this->assertSame(['integration_key' => $supplier->key], $audit->metadata);
        $this->assertStringNotContainsString('original-secret', $audit->toJson());
    }

    public function test_new_integration_supplier_tracks_actor_without_credentials_or_endpoint_in_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Integration Administrator']);
        app()->instance(SupplierRegistry::class, new SupplierRegistry([new ConfigurableRestClient('fake')]));
        $this->actingAs($admin)->post('/admin/dropshipping/suppliers', ['name' => 'New API vendor', 'driver_key' => 'fake', 'base_url' => 'https://supplier.example/api', 'api_key' => 'top-secret-api-key', 'secret_key' => 'top-secret-credentials'])->assertSessionHasNoErrors();
        $supplier = DropshipSupplier::sole();
        $this->assertSame($admin->id, $supplier->created_by);
        $this->assertSame($admin->id, $supplier->updated_by);
        $audit = SupplierAudit::sole();
        $this->assertSame($admin->name, $audit->actor_name);
        $this->assertSame(['driver_key' => 'fake'], $audit->after);
        $this->assertSame(['integration_key' => $supplier->key], $audit->metadata);
        $this->assertStringNotContainsString('top-secret', $audit->toJson());
        $this->assertStringNotContainsString('https://supplier.example', $audit->toJson());
        $admin->delete();
        $this->assertSame('Integration Administrator', $audit->fresh()->actor_name);
        $this->assertNull($audit->fresh()->actor_id);
    }

    public function test_provider_cannot_be_changed_after_catalog_or_imported_products_exist(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$supplier, $source] = $this->source();
        $product = app(ProductImportService::class)->import($source)->product;
        $supplier->update(['api_key' => 'protected-key']);
        app()->instance(SupplierRegistry::class, new SupplierRegistry([new ConfigurableRestClient('fake'), new ConfigurableRestClient('different-provider')]));
        $this->actingAs($admin)->patch("/admin/dropshipping/suppliers/{$supplier->id}", ['name' => 'Supplier', 'driver_key' => 'different-provider', 'base_url' => 'https://different.example/api', 'api_key' => 'replacement-key'])->assertSessionHasErrors('driver_key');
        $this->assertSame('fake', $supplier->fresh()->driver_key);
        $this->assertSame('https://supplier.example', $supplier->fresh()->base_url);
        $this->assertSame('protected-key', $supplier->fresh()->api_key);
        $this->assertSame($supplier->id, $product->fresh()->supplier_id);
        $this->assertSame($source->id, $product->supplierLinks()->first()->supplier_product_row_id);
        $this->assertSame(0, SupplierAudit::where('event', 'integration_updated')->count());
    }

    private function supplier(string $name = 'Supplier'): DropshipSupplier
    {
        return DropshipSupplier::create(['key' => 'vendor-'.uniqid(), 'name' => $name, 'supplier_type' => 'dropshipping', 'driver_key' => 'fake', 'base_url' => 'https://supplier.example', 'is_active' => true, 'pricing_rules' => ['selling_markup' => ['type' => 'percent', 'value' => 20]]]);
    }

    private function source(): array
    {
        $supplier = $this->supplier();
        $category = Category::create(['name' => 'Category', 'slug' => 'category-'.uniqid()]);
        app(CategoryMapper::class)->mapManually($supplier, 'category', $category);
        $source = DropshipSupplierProduct::create(['supplier_id' => $supplier->id, 'supplier_product_id' => 'external-'.uniqid(), 'supplier_category_key' => 'category', 'name' => 'Supplier product', 'product_code' => 'SKU-'.uniqid(), 'currency' => 'BDT', 'cost_price' => 100, 'max_price' => 200, 'stock_qty' => 8, 'is_available' => true, 'raw_payload' => [], 'fetched_at' => now()]);
        return [$supplier, $source];
    }

    private function linked(DropshipSupplierProduct $source, ?int $supplierId = null, bool $imported = true): Product
    {
        $product = Product::create(['category_id' => Category::first()->id, 'name' => 'Legacy product', 'supplier_id' => $supplierId, 'source_type' => 'manual', 'regular_price' => 400, 'stock_quantity' => 16]);
        DropshipProductLink::create(['supplier_product_row_id' => $source->id, 'product_id' => $product->id, 'product_created_by_integration' => $imported, 'field_sync_rules' => ['price' => true, 'stock' => true]]);
        return $product;
    }
}

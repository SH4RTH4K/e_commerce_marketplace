<?php

namespace Tests\Feature;

use App\Jobs\Dropshipping\StartImportedProductSync;
use App\Jobs\Dropshipping\SyncImportedProductItem;
use App\Models\Category;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\DropshipSyncRun;
use App\Models\Product;
use App\Models\User;
use App\Services\Dropshipping\PriceStockSyncService;
use App\Services\Dropshipping\ProductImportService;
use App\Services\Dropshipping\SyncRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportedProductBatchSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dropshipping.enabled' => true]);
    }

    public function test_batch_sync_queues_every_imported_product_matching_the_filters(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $matching = $this->linkedProduct($supplier, 'JBL Flip Speaker', true);
        $this->linkedProduct($supplier, 'JBL Draft Speaker', false);
        $this->linkedProduct($supplier, 'Sony Published Speaker', true);

        $this->actingAs($admin)->post('/admin/dropshipping/imported/bulk-sync-filtered', [
            'q' => 'JBL',
            'status' => 'published',
        ])->assertRedirect();

        $run = DropshipSyncRun::query()->sole();
        $this->assertSame('imported_product_sync', $run->type);
        $this->assertSame([$matching->id], $run->filters['supplier_product_ids']);
        Queue::assertPushed(StartImportedProductSync::class, fn ($job) => $job->syncRunId === $run->id);

        (new StartImportedProductSync($run->id))->handle(app(SyncRunService::class));

        $this->assertDatabaseHas('dropship_sync_runs', [
            'id' => $run->id,
            'status' => 'running',
            'total_items' => 1,
        ]);
        Queue::assertPushed(SyncImportedProductItem::class, fn ($job) => $job->supplierProductRowId === $matching->id);
    }

    public function test_repeated_batch_click_does_not_queue_a_duplicate_supplier_run(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $this->linkedProduct($supplier, 'Portable Speaker', true);

        $this->actingAs($admin)->post('/admin/dropshipping/imported/bulk-sync-filtered')->assertRedirect();
        $this->actingAs($admin)->post('/admin/dropshipping/imported/bulk-sync-filtered')
            ->assertRedirect()
            ->assertSessionHas('status', 'A price and data synchronization is already running for the selected supplier(s).');

        $this->assertDatabaseCount('dropship_sync_runs', 1);
        Queue::assertPushed(StartImportedProductSync::class, 1);
    }

    public function test_imported_product_sync_starts_with_one_item_job_for_a_large_run(): void
    {
        Queue::fake();
        $supplier = $this->supplier();
        $first = $this->linkedProduct($supplier, 'First speaker', true);
        $second = $this->linkedProduct($supplier, 'Second speaker', true);
        $run = app(SyncRunService::class)->createRun($supplier, 'imported_product_sync', filters: [
            'supplier_product_ids' => [$first->id, $second->id],
        ]);

        (new StartImportedProductSync($run->id))->handle(app(SyncRunService::class));

        $this->assertDatabaseHas('dropship_sync_runs', ['id' => $run->id, 'total_items' => 2]);
        Queue::assertPushed(SyncImportedProductItem::class, 1);
        Queue::assertPushed(SyncImportedProductItem::class, fn ($job) => $job->supplierProductRowId === $first->id);
    }

    public function test_pricing_protection_conflict_completes_without_marking_the_product_sync_as_failed(): void
    {
        Queue::fake();
        $supplier = $this->supplier();
        $supplier->update(['pricing_rules' => [
            'selling' => ['base' => 'supplier_cost', 'adjustment_type' => 'none', 'adjustment_value' => 0],
            'minimum' => ['base' => 'supplier_cost', 'adjustment_type' => 'plus_percent', 'adjustment_value' => 20, 'cap_enabled' => true],
            'maximum' => ['adjustment_type' => 'minus_fixed', 'adjustment_value' => 5, 'cap_enabled' => true],
        ]]);
        $source = $this->linkedProduct($supplier, 'Protected price speaker', true);
        $source->update(['cost_price' => 100, 'max_price' => 110]);
        $run = app(SyncRunService::class)->createRun($supplier, 'imported_product_sync', filters: [
            'supplier_product_ids' => [$source->id],
        ]);

        (new StartImportedProductSync($run->id))->handle(app(SyncRunService::class));
        (new SyncImportedProductItem($run->id, $source->id))->handle(
            app(ProductImportService::class),
            app(PriceStockSyncService::class),
            app(SyncRunService::class),
        );

        $this->assertDatabaseHas('dropship_sync_runs', [
            'id' => $run->id,
            'status' => 'completed',
            'success_items' => 1,
            'failed_items' => 0,
        ]);
        $link = DropshipProductLink::query()->where('supplier_product_row_id', $source->id)->firstOrFail();
        $this->assertSame('calculated_minimum_exceeds_maximum', $link->pricing_snapshot['reason']);
    }

    public function test_imported_products_page_exposes_active_price_and_data_sync_runs(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $this->linkedProduct($supplier, 'Portable Speaker', true);

        $this->actingAs($admin)->post('/admin/dropshipping/imported/bulk-sync-filtered')->assertRedirect();

        $this->actingAs($admin)->get('/admin/dropshipping/imported')
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dropshipping/ImportedProducts')
                ->has('sync_runs', 1)
                ->where('sync_runs.0.supplier.name', 'Supplier')
                ->where('sync_runs.0.status', 'queued')
                ->where('sync_runs.0.requested_items', 1)
                ->has('sync_suppliers', 1)
                ->where('sync_suppliers.0.name', 'Supplier')
                ->where('sync_suppliers.0.imported_products_count', 1)
            );
    }

    public function test_supplier_card_sync_only_queues_that_suppliers_imported_products(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $firstSupplier = $this->supplier();
        $secondSupplier = $this->supplier();
        $firstSource = $this->linkedProduct($firstSupplier, 'First supplier speaker', true);
        $this->linkedProduct($secondSupplier, 'Second supplier speaker', true);

        $this->actingAs($admin)->post('/admin/dropshipping/imported/bulk-sync-filtered', [
            'supplier_id' => $firstSupplier->id,
        ])->assertRedirect();

        $run = DropshipSyncRun::query()->sole();
        $this->assertSame($firstSupplier->id, $run->supplier_id);
        $this->assertSame([$firstSource->id], $run->filters['supplier_product_ids']);
    }

    public function test_failed_imported_products_can_be_retried_in_a_new_run(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $source = $this->linkedProduct($supplier, 'Retry speaker', true);
        $syncRuns = app(SyncRunService::class);
        $run = $syncRuns->createRun($supplier, 'imported_product_sync', $admin, [
            'supplier_product_ids' => [$source->id],
        ]);
        $syncRuns->queueItems($run, ['product:'.$source->id]);
        $run->forceFill(['status' => 'completed_with_errors', 'failed_items' => 1, 'processed_items' => 1])->save();
        $run->items()->update(['status' => 'failed', 'error_summary' => 'Supplier API timed out.']);

        $this->actingAs($admin)->post("/admin/dropshipping/runs/{$run->id}/retry-imported")
            ->assertRedirect()
            ->assertSessionHas('status', '1 failed imported product(s) were queued for retry.');

        $retry = DropshipSyncRun::query()->latest('id')->firstOrFail();
        $this->assertSame('imported_product_sync', $retry->type);
        $this->assertSame([$source->id], $retry->filters['supplier_product_ids']);
        $this->assertSame($run->id, $retry->filters['retry_of_run_id']);
        Queue::assertPushed(StartImportedProductSync::class, fn ($job) => $job->syncRunId === $retry->id);
    }

    public function test_imported_products_can_be_ordered_by_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $this->linkedProduct($supplier, 'Alpha Speaker', true);
        $this->linkedProduct($supplier, 'Zulu Speaker', true);

        $this->actingAs($admin)->get('/admin/dropshipping/imported?order_by=name_desc')
            ->assertInertia(fn ($page) => $page
                ->where('order_by', 'name_desc')
                ->where('products.0.name', 'Zulu Speaker')
            );
    }

    public function test_supplier_products_can_be_ordered_by_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $this->linkedProduct($supplier, 'Alpha Speaker', true);
        $this->linkedProduct($supplier, 'Zulu Speaker', true);

        $this->actingAs($admin)->get('/admin/dropshipping/products?order_by=name_desc')
            ->assertInertia(fn ($page) => $page
                ->where('order_by', 'name_desc')
                ->where('products.0.name', 'Zulu Speaker')
            );
    }

    public function test_product_code_is_available_on_supplier_and_imported_product_lists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $source = $this->linkedProduct($supplier, 'Coded Speaker', true);
        $source->update(['product_code' => 'MH-123']);

        $this->actingAs($admin)->get('/admin/dropshipping/products')
            ->assertInertia(fn ($page) => $page
                ->where('products.0.product_code', 'MH-123')
                ->where('products.0.category', 'electronics')
            );

        $this->actingAs($admin)->get('/admin/dropshipping/imported')
            ->assertInertia(fn ($page) => $page
                ->where('products.0.product_code', 'MH-123')
            );
    }

    public function test_product_code_falls_back_to_the_supplier_product_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $source = $this->linkedProduct($supplier, 'Fallback Code Speaker', true);

        $this->actingAs($admin)->get('/admin/dropshipping/products')
            ->assertInertia(fn ($page) => $page
                ->where('products.0.product_code', $source->supplier_product_id)
            );

        $this->actingAs($admin)->get('/admin/dropshipping/imported')
            ->assertInertia(fn ($page) => $page
                ->where('products.0.product_code', $source->supplier_product_id)
            );
    }

    private function supplier(): DropshipSupplier
    {
        return DropshipSupplier::create([
            'key' => 'supplier-'.uniqid(),
            'name' => 'Supplier',
            'driver_key' => 'fake',
            'base_url' => 'https://supplier.example',
            'is_active' => true,
        ]);
    }

    private function linkedProduct(DropshipSupplier $supplier, string $name, bool $published): DropshipSupplierProduct
    {
        $category = Category::firstOrCreate(['slug' => 'electronics'], ['name' => 'Electronics']);
        $source = DropshipSupplierProduct::create([
            'supplier_id' => $supplier->id,
            'supplier_product_id' => 'source-'.uniqid(),
            'supplier_category_key' => 'electronics',
            'name' => $name,
            'currency' => 'BDT',
            'cost_price' => 100,
            'max_price' => 150,
            'stock_qty' => 5,
            'is_available' => true,
            'raw_payload' => [],
            'fetched_at' => now(),
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'regular_price' => 150,
            'stock_quantity' => 5,
            'is_published' => $published,
        ]);
        DropshipProductLink::create([
            'supplier_product_row_id' => $source->id,
            'product_id' => $product->id,
            'sync_status' => 'active',
            'product_created_by_integration' => true,
            'field_sync_rules' => ['price' => true, 'stock' => true],
        ]);

        return $source;
    }
}

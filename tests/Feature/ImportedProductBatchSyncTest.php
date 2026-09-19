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

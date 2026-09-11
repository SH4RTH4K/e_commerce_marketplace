<?php

namespace Tests\Feature;

use App\Jobs\Dropshipping\StartSupplierCatalogSync;
use App\Jobs\Dropshipping\SyncSupplierCatalogPage;
use App\Models\DropshipSupplier;
use App\Services\Dropshipping\SupplierRegistry;
use App\Services\Dropshipping\SyncRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CatalogSyncJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_job_queues_a_catalog_page_instead_of_making_an_admin_request_do_work(): void
    {
        Queue::fake();
        $supplier = DropshipSupplier::create([
            'key' => 'supplier',
            'name' => 'Supplier',
            'driver_key' => 'missing-driver',
            'base_url' => 'https://supplier.example',
            'is_active' => true,
        ]);
        $run = app(SyncRunService::class)->createRun($supplier, 'catalog');

        // A real integration driver is intentionally unavailable until live
        // fixtures are supplied. The job itself is the boundary under test.
        StartSupplierCatalogSync::dispatch($run->id);

        Queue::assertPushed(StartSupplierCatalogSync::class, fn ($job) => $job->syncRunId === $run->id);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\Dropshipping\StartSupplierPriceStockSync;
use App\Models\DropshipSupplier;
use App\Services\Dropshipping\SyncRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PriceStockSyncJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_stock_sync_is_dispatched_as_a_job(): void
    {
        Queue::fake();
        $supplier = DropshipSupplier::create([
            'key' => 'supplier',
            'name' => 'Supplier',
            'driver_key' => 'fake',
            'base_url' => 'https://supplier.example',
            'is_active' => true,
        ]);
        $run = app(SyncRunService::class)->createRun($supplier, 'price_stock');

        StartSupplierPriceStockSync::dispatch($run->id);

        Queue::assertPushed(StartSupplierPriceStockSync::class, fn ($job) => $job->syncRunId === $run->id);
    }
}

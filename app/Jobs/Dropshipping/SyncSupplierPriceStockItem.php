<?php

namespace App\Jobs\Dropshipping;

use App\Models\DropshipSupplierProduct;
use App\Models\DropshipSyncRun;
use App\Services\Dropshipping\PriceStockSyncService;
use App\Services\Dropshipping\SyncRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncSupplierPriceStockItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $syncRunId, public int $supplierProductRowId)
    {
    }

    public function handle(PriceStockSyncService $priceStockSync, SyncRunService $syncRuns): void
    {
        $run = DropshipSyncRun::query()->findOrFail($this->syncRunId);
        $source = DropshipSupplierProduct::query()->findOrFail($this->supplierProductRowId);
        $itemKey = 'product:' . $source->id;

        if ($source->supplier_id !== $run->supplier_id || ! $syncRuns->startItem($run, $itemKey)) {
            return;
        }

        try {
            $priceStockSync->sync($source);
        } catch (Throwable $exception) {
            $syncRuns->requeueItem($run, $itemKey);

            throw $exception;
        }

        // Pricing safeguards can leave the existing price in place while
        // stock and other safe fields still synchronize successfully.
        $syncRuns->succeedItem($run, $itemKey);

        $syncRuns->finalize($run);
    }

    public function failed(Throwable $exception): void
    {
        $run = DropshipSyncRun::query()->find($this->syncRunId);
        if ($run === null) {
            return;
        }

        $syncRuns = app(SyncRunService::class);
        $syncRuns->failPendingItem($run, 'product:' . $this->supplierProductRowId, 'Supplier price and stock could not be synchronized.');
        $syncRuns->finalize($run);
    }
}

<?php

namespace App\Jobs\Dropshipping;

use App\Models\DropshipSupplierProduct;
use App\Models\DropshipSyncRun;
use App\Services\Dropshipping\PriceStockSyncService;
use App\Services\Dropshipping\ProductImportService;
use App\Services\Dropshipping\SyncRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncImportedProductItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        public int $syncRunId,
        public int $supplierProductRowId,
        public bool $dispatchNextItem = true,
    )
    {
    }

    public function handle(ProductImportService $importService, PriceStockSyncService $priceStockSync, SyncRunService $syncRuns): void
    {
        $run = DropshipSyncRun::query()->findOrFail($this->syncRunId);
        $source = DropshipSupplierProduct::query()->findOrFail($this->supplierProductRowId);
        $itemKey = 'product:' . $source->id;
        if ($source->supplier_id !== $run->supplier_id || ! $syncRuns->startItem($run, $itemKey)) {
            return;
        }

        try {
            $importService->import($source);
            $priceStockSync->sync($source);
        } catch (Throwable $exception) {
            if (! $this->dispatchNextItem) {
                $syncRuns->failItem($run, $itemKey, 'Imported product could not be synchronized.');
                $syncRuns->finalize($run);

                return;
            }

            $syncRuns->requeueItem($run, $itemKey);

            throw $exception;
        }

        // A pricing conflict is a deliberate price-protection rule, not a
        // failed sync. Stock and description updates can still be applied.
        $syncRuns->succeedItem($run, $itemKey);
        if ($this->dispatchNextItem) {
            $this->queueNextItem($run, $syncRuns);
        } else {
            $syncRuns->finalize($run);
        }
    }

    public function failed(Throwable $exception): void
    {
        $run = DropshipSyncRun::query()->find($this->syncRunId);
        if ($run === null) {
            return;
        }

        $syncRuns = app(SyncRunService::class);
        $syncRuns->failPendingItem($run, 'product:' . $this->supplierProductRowId, 'Imported product could not be synchronized.');
        if ($this->dispatchNextItem) {
            $this->queueNextItem($run, $syncRuns);
        } else {
            $syncRuns->finalize($run);
        }
    }

    private function queueNextItem(DropshipSyncRun $run, SyncRunService $syncRuns): void
    {
        $run = DropshipSyncRun::query()->find($run->id);
        if ($run === null || $run->status !== 'running') {
            return;
        }

        $next = $run->items()
            ->where('status', 'queued')
            ->orderBy('id')
            ->first(['item_key']);

        if ($next !== null && preg_match('/^product:(\d+)$/', $next->item_key, $matches)) {
            self::dispatch($run->id, (int) $matches[1]);

            return;
        }

        $syncRuns->finalize($run);
    }
}

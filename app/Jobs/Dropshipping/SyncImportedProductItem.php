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

    public function __construct(public int $syncRunId, public int $supplierProductRowId)
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
            $result = $priceStockSync->sync($source);
        } catch (Throwable $exception) {
            $syncRuns->requeueItem($run, $itemKey);

            throw $exception;
        }

        $hasPricingConflict = collect($result->warnings)->contains(
            fn (string $warning): bool => str_starts_with($warning, 'pricing_') || $warning === 'unsupported_currency',
        );
        if ($hasPricingConflict) {
            $syncRuns->failItem($run, $itemKey, 'Supplier price could not be synchronized within configured constraints.');
        } else {
            $syncRuns->succeedItem($run, $itemKey);
        }
        $syncRuns->finalize($run);
    }

    public function failed(Throwable $exception): void
    {
        $run = DropshipSyncRun::query()->find($this->syncRunId);
        if ($run === null) {
            return;
        }

        $syncRuns = app(SyncRunService::class);
        $syncRuns->failPendingItem($run, 'product:' . $this->supplierProductRowId, 'Imported product could not be synchronized.');
        $syncRuns->finalize($run);
    }
}
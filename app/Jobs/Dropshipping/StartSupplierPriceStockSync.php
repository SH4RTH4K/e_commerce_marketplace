<?php

namespace App\Jobs\Dropshipping;

use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\DropshipSyncRun;
use App\Services\Dropshipping\Support\SyncRunState;
use App\Services\Dropshipping\SyncRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class StartSupplierPriceStockSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $syncRunId)
    {
    }

    public function handle(SyncRunService $syncRuns): void
    {
        $run = DropshipSyncRun::query()->findOrFail($this->syncRunId);
        $supplier = DropshipSupplier::query()->findOrFail($run->supplier_id);

        if (! $supplier->is_active || $run->status === SyncRunState::CANCELLED) {
            $syncRuns->failRun($run, 'Supplier is inactive or the price and stock run was cancelled before it started.');

            return;
        }
        if ($run->status === SyncRunState::QUEUED && ! $syncRuns->start($run)) {
            return;
        }
        if ($run->fresh()->status !== SyncRunState::RUNNING) {
            return;
        }

        $queuedAnything = false;
        DropshipSupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->whereHas('productLink', fn ($query) => $query
                ->where('sync_status', 'active')
                ->whereNotNull('product_id'))
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($run, $syncRuns, &$queuedAnything): void {
                $keys = $products->map(fn (DropshipSupplierProduct $product): string => 'product:' . $product->id)->all();
                $syncRuns->queueItems($run, $keys);

                foreach ($products as $product) {
                    SyncSupplierPriceStockItem::dispatch($run->id, $product->id);
                    $queuedAnything = true;
                }
            });

        if (! $queuedAnything) {
            $syncRuns->finalize($run);
        }
    }

    public function failed(Throwable $exception): void
    {
        $run = DropshipSyncRun::query()->find($this->syncRunId);
        if ($run !== null) {
            app(SyncRunService::class)->failRun($run, 'Price and stock sync could not be started.');
        }
    }
}

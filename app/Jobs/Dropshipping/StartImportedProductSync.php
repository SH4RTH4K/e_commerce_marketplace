<?php

namespace App\Jobs\Dropshipping;

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

class StartImportedProductSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public int $syncRunId)
    {
    }

    public function handle(SyncRunService $syncRuns): void
    {
        $run = DropshipSyncRun::query()->with('supplier')->findOrFail($this->syncRunId);
        if ($run->status === SyncRunState::CANCELLED || ! $run->supplier?->is_active) {
            $syncRuns->failRun($run, 'The supplier is inactive or the imported-product run was cancelled before it started.');

            return;
        }
        if ($run->status === SyncRunState::QUEUED && ! $syncRuns->start($run)) {
            return;
        }
        if ($run->fresh()->status !== SyncRunState::RUNNING) {
            return;
        }

        $ids = is_array($run->filters) ? ($run->filters['supplier_product_ids'] ?? []) : [];
        $queued = DropshipSupplierProduct::query()
            ->where('supplier_id', $run->supplier_id)
            ->whereIn('id', $ids)
            ->whereHas('productLink', fn ($query) => $query
                ->where('product_created_by_integration', true)
                ->where('sync_status', 'active')
                ->whereNotNull('product_id'))
            ->pluck('id');

        $keys = $queued->map(fn (int $id): string => 'product:' . $id)->all();
        $syncRuns->queueItems($run, $keys);
        foreach ($queued as $id) {
            SyncImportedProductItem::dispatch($run->id, $id);
        }
        if ($queued->isEmpty()) {
            $syncRuns->finalize($run);
        }
    }

    public function failed(Throwable $exception): void
    {
        $run = DropshipSyncRun::query()->find($this->syncRunId);
        if ($run !== null) {
            app(SyncRunService::class)->failRun($run, 'Imported-product synchronization could not be started.');
        }
    }
}
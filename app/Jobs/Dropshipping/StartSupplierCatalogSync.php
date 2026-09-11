<?php

namespace App\Jobs\Dropshipping;

use App\Models\DropshipSupplier;
use App\Models\DropshipSyncRun;
use App\Services\Dropshipping\SupplierCatalogMirrorService;
use App\Services\Dropshipping\SupplierRegistry;
use App\Services\Dropshipping\Support\SyncRunState;
use App\Services\Dropshipping\SyncRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class StartSupplierCatalogSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $syncRunId)
    {
    }

    public function handle(
        SupplierRegistry $registry,
        SupplierCatalogMirrorService $mirror,
        SyncRunService $syncRuns,
    ): void {
        $run = DropshipSyncRun::query()->findOrFail($this->syncRunId);
        $supplier = DropshipSupplier::query()->findOrFail($run->supplier_id);

        if (! $supplier->is_active || $run->status === SyncRunState::CANCELLED) {
            $syncRuns->failRun($run, 'Supplier is inactive or the catalog run was cancelled before it started.');

            return;
        }

        if ($run->status === SyncRunState::QUEUED && ! $syncRuns->start($run)) {
            return;
        }
        if ($run->fresh()->status !== SyncRunState::RUNNING) {
            return;
        }

        $client = $registry->forDriver($supplier->driver_key);
        if ($client->capabilities()->categories) {
            foreach ($client->fetchCategories($supplier, bypassCache: true) as $category) {
                $mirror->mirrorCategory($supplier, $category);
            }
        }

        if ($syncRuns->queueItems($run, ['page:1']) === 1) {
            SyncSupplierCatalogPage::dispatch($run->id, 1);
        }
    }

    public function failed(Throwable $exception): void
    {
        $run = DropshipSyncRun::query()->find($this->syncRunId);
        if ($run !== null) {
            app(SyncRunService::class)->failRun($run, 'Catalog sync could not be started.');
        }
    }
}

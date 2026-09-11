<?php

namespace App\Jobs\Dropshipping;

use App\Models\DropshipSupplier;
use App\Models\DropshipSyncRun;
use App\Services\Dropshipping\DTO\SupplierProductPage;
use App\Services\Dropshipping\DTO\SupplierCategory;
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

class SyncSupplierCatalogPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $syncRunId, public int $page)
    {
    }

    public function handle(
        SupplierRegistry $registry,
        SupplierCatalogMirrorService $mirror,
        SyncRunService $syncRuns,
    ): void {
        $run = DropshipSyncRun::query()->findOrFail($this->syncRunId);
        $supplier = DropshipSupplier::query()->findOrFail($run->supplier_id);
        $itemKey = 'page:' . $this->page;

        if (! $supplier->is_active || ! $syncRuns->startItem($run, $itemKey)) {
            return;
        }

        $filters = is_array($run->filters) ? $run->filters : [];
        try {
            $result = $registry->forDriver($supplier->driver_key)->fetchProductsPage(
                $supplier,
                $this->page,
                $filters,
                bypassCache: true,
            );
        } catch (Throwable $exception) {
            // Return the item to queued before Laravel retries the job. This
            // preserves duplicate-job protection while allowing a real retry.
            $syncRuns->requeueItem($run, $itemKey);

            throw $exception;
        }

        $mirrorFailures = 0;
        foreach ($result->items as $product) {
            try {
                // Some suppliers expose category IDs only inside each product
                // and do not provide a separate categories endpoint. When the
                // profile maps that value as categoryKey, mirror it as a
                // selectable supplier category instead of losing it.
                if (is_string($product->categoryKey) && trim($product->categoryKey) !== '') {
                    $categoryName = 'Supplier category ' . trim($product->categoryKey);
                    $rawCategoryName = $product->rawPayload['category_name'] ?? null;
                    if (is_scalar($rawCategoryName) && trim((string) $rawCategoryName) !== '') {
                        $categoryName = trim((string) $rawCategoryName);
                    }
                    $mirror->mirrorCategory($supplier, new SupplierCategory(
                        trim($product->categoryKey),
                        $categoryName,
                        rawPayload: ['source' => 'product', 'category_key' => trim($product->categoryKey)],
                    ));
                }
                $mirror->mirrorProduct($supplier, $product);
            } catch (Throwable) {
                // Continue with remaining products; raw exception messages can
                // contain supplier response fragments and are never persisted.
                ++$mirrorFailures;
            }
        }

        if ($mirrorFailures > 0) {
            $syncRuns->failItem($run, $itemKey, "{$mirrorFailures} product mirror operation(s) failed.");
        } else {
            $syncRuns->succeedItem($run, $itemKey);
        }

        $this->queueNextPage($result, $run, $syncRuns);
    }

    public function failed(Throwable $exception): void
    {
        $run = DropshipSyncRun::query()->find($this->syncRunId);
        if ($run === null) {
            return;
        }

        app(SyncRunService::class)->failPendingItem($run, 'page:' . $this->page, 'Catalog page could not be refreshed.');
        app(SyncRunService::class)->finalize($run);
    }

    private function queueNextPage(SupplierProductPage $result, DropshipSyncRun $run, SyncRunService $syncRuns): void
    {
        if (! $result->hasNextPage()) {
            $syncRuns->finalize($run);

            return;
        }

        // DTO validation guarantees that an explicit next page advances. When
        // only last_page is known, move one page forward. Unknown pagination
        // ends after this page rather than guessing or looping indefinitely.
        $nextPage = $result->nextPage ?? ($result->currentPage + 1);
        if ($syncRuns->queueItems($run, ['page:' . $nextPage]) === 1) {
            self::dispatch($run->id, $nextPage);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\Dropshipping\StartSupplierCatalogSync;
use App\Jobs\Dropshipping\StartSupplierPriceStockSync;
use App\Jobs\Dropshipping\SyncSupplierPriceStockItem;
use App\Models\DropshipSupplier;
use App\Services\Dropshipping\SyncRunService;
use Illuminate\Console\Command;

class DropshipSyncCommand extends Command
{
    protected $signature = 'dropship:sync
        {supplier : Supplier business key, for example mohasagor-main}
        {--catalog : Refresh the supplier catalog mirror}
        {--price-stock : Queue price and stock synchronization}
        {--images : Request image synchronization (not available yet)}
        {--product= : Limit price and stock sync to one supplier product ID}';

    protected $description = 'Queue a dropshipping supplier synchronization run';

    public function handle(SyncRunService $syncRuns): int
    {
        if (! (bool) config('dropshipping.enabled')) {
            $this->error('Dropshipping integration is disabled. Set DROPSHIPPING_ENABLED=true after staging approval.');

            return self::FAILURE;
        }

        $supplier = DropshipSupplier::query()->where('key', $this->argument('supplier'))->first();
        if ($supplier === null) {
            $this->error('Supplier was not found.');

            return self::FAILURE;
        }
        if (! $supplier->is_active) {
            $this->error('Supplier is inactive.');

            return self::FAILURE;
        }
        if ($this->option('images')) {
            $this->error('Image synchronization is not available until the hardened image pipeline is implemented.');

            return self::FAILURE;
        }

        $productId = $this->option('product');
        $catalog = (bool) $this->option('catalog');
        $priceStock = (bool) $this->option('price-stock') || $productId !== null;
        if (($catalog ? 1 : 0) + ($priceStock ? 1 : 0) !== 1) {
            $this->error('Choose exactly one operation: --catalog or --price-stock (or --product=...).');

            return self::FAILURE;
        }

        if ($catalog) {
            $run = $syncRuns->createRun($supplier, 'catalog');
            StartSupplierCatalogSync::dispatch($run->id);
            $this->info("Catalog sync queued (run {$run->id}).");

            return self::SUCCESS;
        }

        $filters = $productId === null ? [] : ['product' => (string) $productId];
        $run = $syncRuns->createRun($supplier, 'price_stock', filters: $filters);
        if ($productId === null) {
            StartSupplierPriceStockSync::dispatch($run->id);
            $this->info("Price and stock sync queued (run {$run->id}).");

            return self::SUCCESS;
        }

        $source = $supplier->products()->where('supplier_product_id', (string) $productId)->first();
        if ($source === null) {
            $syncRuns->failRun($run, 'Supplier product was not found in the local mirror.');
            $this->error('Supplier product was not found in the local mirror.');

            return self::FAILURE;
        }

        $syncRuns->start($run);
        $syncRuns->queueItems($run, ['product:' . $source->id]);
        SyncSupplierPriceStockItem::dispatch($run->id, $source->id);
        $this->info("Price and stock sync queued for product (run {$run->id}).");

        return self::SUCCESS;
    }
}

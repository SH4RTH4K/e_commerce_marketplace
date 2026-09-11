<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\Dropshipping\StartSupplierCatalogSync;
use App\Jobs\Dropshipping\StartSupplierPriceStockSync;
use App\Models\DropshipSupplier;
use App\Models\DropshipSyncRun;
use App\Services\Dropshipping\SyncRunService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if ((bool) config('dropshipping.enabled')) {
    Schedule::call(function (): void {
        DropshipSupplier::query()->where('is_active', true)->each(function (DropshipSupplier $supplier): void {
            $hasActiveRun = DropshipSyncRun::query()
                ->where('supplier_id', $supplier->id)
                ->whereIn('status', ['queued', 'running'])
                ->where('type', 'catalog')
                ->exists();
            if ($hasActiveRun) {
                return;
            }

            $run = app(SyncRunService::class)->createRun($supplier, 'catalog');
            StartSupplierCatalogSync::dispatch($run->id);
        });
    })
        ->name('dropshipping-catalog-sync')
        ->cron((string) config('dropshipping.schedule.catalog_cron', '0 2 * * *'))
        ->withoutOverlapping(120)
        ->onOneServer();

    Schedule::call(function (): void {
        DropshipSupplier::query()->where('is_active', true)->each(function (DropshipSupplier $supplier): void {
            $hasActiveRun = DropshipSyncRun::query()
                ->where('supplier_id', $supplier->id)
                ->whereIn('status', ['queued', 'running'])
                ->where('type', 'price_stock')
                ->exists();
            if ($hasActiveRun) {
                return;
            }

            $run = app(SyncRunService::class)->createRun($supplier, 'price_stock');
            StartSupplierPriceStockSync::dispatch($run->id);
        });
    })
        ->name('dropshipping-price-stock-sync')
        ->cron((string) config('dropshipping.schedule.price_stock_cron', '0 * * * *'))
        ->withoutOverlapping(60)
        ->onOneServer();

    Schedule::command('dropship:prune')
        ->dailyAt('03:30')
        ->withoutOverlapping(120)
        ->onOneServer();
}

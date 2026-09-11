<?php

namespace App\Console\Commands;

use App\Models\DropshipDriverProfile;
use App\Models\DropshipSyncRun;
use App\Services\Dropshipping\SupplierCatalogMirrorService;
use App\Services\Dropshipping\DTO\SupplierCategory;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('dropship:fix-category-field')]
#[Description('Fix the mohasagor driver profile category field mapping and backfill categories from existing products')]
class FixMohasagorCategoryMapping extends Command
{
    public function handle(SupplierCatalogMirrorService $mirror): int
    {
        // Step 1: Fix the field mapping - category_key should map to 'category' not 'category_id'
        $profile = DropshipDriverProfile::where('key', 'mohasagor')->first();
        if ($profile === null) {
            $this->error('Mohasagor driver profile not found.');
            return self::FAILURE;
        }

        $mapping = is_array($profile->field_mapping) ? $profile->field_mapping : [];
        $fields  = is_array($mapping['fields'] ?? null) ? $mapping['fields'] : [];

        $old = $fields['category_key'] ?? '(none)';
        $fields['category_key']  = 'category';
        $mapping['fields']       = $fields;
        $profile->field_mapping  = $mapping;
        $profile->save();

        $this->info("Updated category_key mapping: '{$old}' -> 'category'");

        // Step 2: Backfill supplier categories from already-mirrored product raw_payloads
        $supplier = DropshipSupplier::where('driver_key', 'mohasagor')->first();
        if ($supplier === null) {
            $this->warn('No mohasagor supplier found to backfill categories.');
            return self::SUCCESS;
        }

        $this->info("Backfilling supplier categories from existing products for '{$supplier->name}'...");

        $seen    = 0;
        $created = 0;

        DropshipSupplierProduct::where('supplier_id', $supplier->id)
            ->chunkById(200, function ($products) use ($supplier, $mirror, &$seen, &$created) {
                foreach ($products as $product) {
                    $raw = $product->getRawOriginal('raw_payload');
                    $payload = is_string($raw) ? json_decode($raw, true) : (is_array($product->raw_payload) ? $product->raw_payload : null);
                    if (! is_array($payload)) {
                        continue;
                    }

                    $categoryName = trim((string) ($payload['category'] ?? ''));
                    if ($categoryName === '') {
                        continue;
                    }

                    $seen++;
                    try {
                        $mirror->mirrorCategory($supplier, new SupplierCategory(
                            $categoryName,
                            $categoryName,
                            rawPayload: ['source' => 'backfill', 'category' => $categoryName],
                        ));
                        $created++;
                    } catch (\Throwable $e) {
                        $this->warn("Failed to mirror category '{$categoryName}': " . $e->getMessage());
                    }
                }
            });

        $this->info("Backfilled {$created} categories from {$seen} products with a category value.");

        // Step 3a: Backfill supplier_category_key on existing products (links product → category row)
        $linked = 0;
        DropshipSupplierProduct::where('supplier_id', $supplier->id)
            ->whereNull('supplier_category_key')
            ->chunkById(200, function ($products) use (&$linked) {
                foreach ($products as $product) {
                    $raw = $product->getRawOriginal('raw_payload');
                    $payload = is_string($raw) ? json_decode($raw, true) : null;
                    if (! is_array($payload)) {
                        continue;
                    }
                    $cat = trim((string) ($payload['category'] ?? ''));
                    if ($cat === '') {
                        continue;
                    }
                    $product->supplier_category_key = $cat;
                    $product->saveQuietly();
                    $linked++;
                }
            });
        $this->info("Updated supplier_category_key on {$linked} products.");

        // Step 4: Finalize any stuck "running" sync runs
        $stuck = DropshipSyncRun::where('status', 'running')->get();
        foreach ($stuck as $run) {
            $hasPending = $run->items()->whereIn('status', ['queued', 'running'])->exists();
            if (! $hasPending) {
                $status = $run->failed_items > 0 ? 'completed_with_errors' : 'completed';
                $run->update(['status' => $status, 'finished_at' => now()]);
                $this->info("Finalized stuck run #{$run->id} -> {$status}");
            }
        }

        $this->info('Done. Refresh the Category Mapping page.');

        return self::SUCCESS;
    }
}


<?php

namespace App\Services\Dropshipping;

use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/** The existing supplier row is the stable business owner of its API integration. */
class SupplierAssociationService
{
    public function resolve(DropshipSupplierProduct $source): DropshipSupplier
    {
        $supplier = $source->supplier;
        if ($supplier === null || ! $this->hasIntegration($supplier)) {
            throw new SupplierOwnershipException('missing_supplier_connection', 'Link an active supplier in Dropshipping API Settings before importing or synchronizing this product.');
        }
        if (! $supplier->is_active) {
            throw new SupplierOwnershipException('inactive_supplier', 'Activate and test this supplier connection before importing or synchronizing products.');
        }

        return $supplier;
    }

    public function hasIntegration(DropshipSupplier $supplier): bool
    {
        return trim((string) $supplier->driver_key) !== '' && $supplier->driver_key !== 'manual';
    }

    /** Validate all ownership before touching content, images, prices, or stock. */
    public function assertOwned(DropshipSupplierProduct $source, Product $product, DropshipProductLink $link): void
    {
        $supplier = $this->resolve($source);
        if (! $link->product_created_by_integration) {
            throw new SupplierOwnershipException('manual_product_protected', 'This mapping does not own the local product. Use the controlled relinking workflow; manual inventory cannot be synchronized automatically.');
        }
        if ($product->supplier_id !== null && $product->source_type !== 'dropshipping') {
            throw new SupplierOwnershipException('manual_product_protected', 'This product is maintained as manual inventory. Review its source and external mapping before synchronizing.');
        }
        if ($product->supplier_id !== null && (int) $product->supplier_id !== (int) $supplier->id) {
            throw new SupplierOwnershipException('supplier_ownership_conflict', 'The product belongs to another supplier. Review the existing mapping and use the controlled relinking workflow.');
        }
        if (DropshipProductLink::query()->where('product_id', $product->id)->whereKeyNot($link->id)->exists()) {
            throw new SupplierOwnershipException('ambiguous_supplier_mapping', 'Multiple external products reference this local product. Review the mappings before synchronizing.');
        }
        if ($product->supplier_id === null) {
            $product->forceFill(['supplier_id' => $supplier->id, 'source_type' => 'dropshipping'])->saveQuietly();
        }
    }

    /** Safe to run repeatedly, including from an additive data migration. No supplier is created. */
    public function backfill(): array
    {
        $report = ['updated' => 0, 'unchanged' => 0, 'conflicts' => []];
        Product::query()->whereHas('supplierLinks', fn ($query) => $query->where('product_created_by_integration', true))
            ->orderBy('id')->chunkById(100, function ($products) use (&$report): void {
                foreach ($products as $candidate) {
                    DB::transaction(function () use ($candidate, &$report): void {
                        $product = Product::query()->lockForUpdate()->findOrFail($candidate->id);
                        $links = $product->supplierLinks()->with('supplierProduct.supplier')->get();
                        $owners = $links->pluck('supplierProduct.supplier_id')->filter()->unique()->values();
                        $supplier = $links->first()?->supplierProduct?->supplier;
                        $conflict = $links->count() !== 1 || $owners->count() !== 1 || $supplier === null || ! $this->hasIntegration($supplier)
                            ? 'ambiguous_or_missing_supplier_mapping'
                            : ($product->supplier_id !== null && (int) $product->supplier_id !== (int) $owners->first() ? 'supplier_ownership_conflict' : null);
                        if ($conflict !== null) {
                            $report['conflicts'][] = ['product_id' => $product->id, 'supplier_id' => $product->supplier_id, 'mapped_supplier_ids' => $owners->all(), 'reason' => $conflict];
                            return;
                        }
                        $changes = [];
                        if ($product->supplier_id === null) $changes['supplier_id'] = $owners->first();
                        if ($product->source_type !== 'dropshipping') $changes['source_type'] = 'dropshipping';
                        if ($changes === []) { ++$report['unchanged']; return; }
                        $product->forceFill($changes)->saveQuietly();
                        ++$report['updated'];
                    });
                }
            });

        return $report;
    }
}

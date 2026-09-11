<?php

namespace App\Services\Dropshipping;

use App\Models\DropshipSupplierProduct;
use App\Models\DropshipVariantLink;
use App\Models\ProductVariant;

class SupplierVariantAutoMapper
{
    public function sync(DropshipSupplierProduct $supplierProduct): int
    {
        $productId = $supplierProduct->productLink?->product_id;
        if (! $productId) {
            return 0;
        }

        $mapped = 0;
        foreach ($supplierProduct->variants as $supplierVariant) {
            $attributes = is_array($supplierVariant->attributes) ? $supplierVariant->attributes : [];
            if (count($attributes) !== 2 || ! isset($attributes['type'], $attributes['value'])) {
                continue;
            }

            $type = trim((string) $attributes['type']);
            $value = trim((string) $attributes['value']);
            if ($type === '' || $value === '') {
                continue;
            }

            $localVariant = ProductVariant::query()->firstOrCreate(
                ['product_id' => $productId, 'type' => $type, 'value' => $value],
                [
                    'price_delta' => 0,
                    'stock' => $this->stock($supplierVariant->stock_qty, $supplierVariant->is_available),
                    'position' => ProductVariant::query()->where('product_id', $productId)->count(),
                ],
            );

            DropshipVariantLink::query()->updateOrCreate(
                ['supplier_variant_row_id' => $supplierVariant->getKey()],
                ['product_variant_id' => $localVariant->getKey()],
            );
            $mapped++;
        }

        return $mapped;
    }

    private function stock(mixed $stock, mixed $available): int
    {
        if ($available === false || $stock === null) {
            return 0;
        }

        return min(4_294_967_295, max(0, (int) floor((float) $stock)));
    }
}

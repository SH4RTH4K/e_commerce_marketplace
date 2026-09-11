<?php

namespace App\Services\Dropshipping;

use App\Models\Category;
use App\Models\DropshipCategoryMapping;
use App\Models\DropshipSupplier;
use App\Services\Dropshipping\Support\CategoryMappingMode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CategoryMapper
{
    public function localCategoryFor(DropshipSupplier $supplier, string $supplierCategoryKey): ?Category
    {
        $mapping = DropshipCategoryMapping::query()
            ->with('category')
            ->where('supplier_id', $supplier->getKey())
            ->where('supplier_category_key', $this->key($supplierCategoryKey))
            ->first();

        return $mapping?->category;
    }

    public function mapManually(
        DropshipSupplier $supplier,
        string $supplierCategoryKey,
        Category $category,
    ): DropshipCategoryMapping {
        if (! $supplier->exists || ! $category->exists) {
            throw new InvalidArgumentException('Supplier and local category must be stored before mapping.');
        }

        $key = $this->key($supplierCategoryKey);
        $now = now();

        DB::table('dropship_category_mappings')->upsert([
            [
                'supplier_id' => $supplier->getKey(),
                'supplier_category_key' => $key,
                'category_id' => $category->getKey(),
                'mapping_mode' => CategoryMappingMode::MANUAL,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['supplier_id', 'supplier_category_key'], ['category_id', 'mapping_mode', 'updated_at']);

        return DropshipCategoryMapping::query()
            ->where('supplier_id', $supplier->getKey())
            ->where('supplier_category_key', $key)
            ->firstOrFail();
    }

    public function unmap(DropshipSupplier $supplier, string $supplierCategoryKey): bool
    {
        return DropshipCategoryMapping::query()
            ->where('supplier_id', $supplier->getKey())
            ->where('supplier_category_key', $this->key($supplierCategoryKey))
            ->delete() > 0;
    }

    private function key(string $supplierCategoryKey): string
    {
        $key = trim($supplierCategoryKey);
        if ($key === '') {
            throw new InvalidArgumentException('A supplier category key is required.');
        }

        return $key;
    }
}

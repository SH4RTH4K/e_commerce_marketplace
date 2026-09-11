<?php

namespace App\Services\Dropshipping;

use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierCategory;
use App\Models\DropshipSupplierProduct;
use App\Services\Dropshipping\DTO\SupplierCategory;
use App\Services\Dropshipping\DTO\SupplierProduct;
use App\Services\Dropshipping\DTO\SupplierVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

class SupplierCatalogMirrorService
{
    public function __construct(private readonly SupplierVariantAutoMapper $variantMapper)
    {
    }

    public function mirrorCategory(DropshipSupplier $supplier, SupplierCategory $category): DropshipSupplierCategory
    {
        $now = now();
        DB::table('dropship_supplier_categories')->upsert([
            [
                'supplier_id' => $supplier->getKey(),
                'supplier_category_key' => $category->key,
                'parent_category_key' => $category->parentKey,
                'name' => Str::limit($category->name, 255, ''),
                'path' => $category->path,
                'level' => $category->level,
                'raw_payload' => $this->json($category->rawPayload),
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['supplier_id', 'supplier_category_key'], [
            'parent_category_key', 'name', 'path', 'level', 'raw_payload', 'last_seen_at', 'updated_at',
        ]);

        return DropshipSupplierCategory::query()
            ->where('supplier_id', $supplier->getKey())
            ->where('supplier_category_key', $category->key)
            ->firstOrFail();
    }

    public function mirrorProduct(DropshipSupplier $supplier, SupplierProduct $product): DropshipSupplierProduct
    {
        return DB::transaction(function () use ($supplier, $product): DropshipSupplierProduct {
            $now = now();
            $rawPayloadData = $product->rawPayload;
            if (! array_key_exists('description', $rawPayloadData) && $product->description !== null) {
                $rawPayloadData['description'] = $product->description;
            }
            $rawPayload = $this->json($rawPayloadData);
            DB::table('dropship_supplier_products')->upsert([
                [
                    'supplier_id' => $supplier->getKey(),
                    'supplier_product_id' => $product->id,
                    'supplier_category_key' => $product->categoryKey,
                    'name' => Str::limit($product->name, 255, ''),
                    'product_code' => $product->productCode === null ? null : Str::limit($product->productCode, 255, ''),
                    'currency' => $product->currency,
                    'cost_price' => $product->costPrice,
                    'max_price' => $product->maxPrice,
                    'stock_qty' => $product->stockQuantity,
                    'is_available' => $product->isAvailable,
                    'supplier_status' => $product->status,
                    'raw_payload' => $rawPayload,
                    'payload_hash' => hash('sha256', $rawPayload),
                    'fetched_at' => $now,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ], ['supplier_id', 'supplier_product_id'], [
                'supplier_category_key', 'name', 'product_code', 'currency', 'cost_price', 'max_price', 'stock_qty',
                'is_available', 'supplier_status', 'raw_payload', 'payload_hash', 'fetched_at', 'last_seen_at', 'updated_at',
            ]);

            $mirrored = DropshipSupplierProduct::query()
                ->where('supplier_id', $supplier->getKey())
                ->where('supplier_product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->mirrorVariants($mirrored, $product->variants, $product->currency, $now);
            $this->variantMapper->sync($mirrored->load('variants', 'productLink'));

            return $mirrored->refresh();
        });
    }

    /**
     * @param list<SupplierVariant> $variants
     */
    private function mirrorVariants(DropshipSupplierProduct $product, array $variants, string $productCurrency, mixed $now): void
    {
        if ($variants === []) {
            return;
        }

        $rows = array_map(fn (SupplierVariant $variant): array => [
            'supplier_product_row_id' => $product->getKey(),
            'supplier_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'attributes' => $this->json($variant->attributes),
            'currency' => $variant->currency ?? $productCurrency,
            'cost_price' => $variant->costPrice,
            'max_price' => $variant->maxPrice,
            'stock_qty' => $variant->stockQuantity,
            'is_available' => $variant->isAvailable,
            'raw_payload' => $this->json($variant->rawPayload),
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $variants);

        DB::table('dropship_supplier_variants')->upsert($rows, [
            'supplier_product_row_id', 'supplier_variant_id',
        ], [
            'sku', 'attributes', 'currency', 'cost_price', 'max_price', 'stock_qty', 'is_available',
            'raw_payload', 'last_seen_at', 'updated_at',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): string
    {
        try {
            return json_encode(
                $this->sortPayload($payload),
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            // A normalizer must provide arrays only. This defensive fallback
            // keeps a malformed optional payload from breaking a catalog run.
            return '{}';
        }
    }

    private function sortPayload(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortPayload($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->sortPayload($item), $value);
    }
}

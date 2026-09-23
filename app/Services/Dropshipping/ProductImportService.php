<?php

namespace App\Services\Dropshipping;

use App\Models\DropshipProductLink;
use App\Models\DropshipSupplierProduct;
use App\Models\ProductImage;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportService
{
    public function __construct(
        private readonly CategoryMapper $categoryMapper,
        private readonly PricingEngine $pricingEngine,
        private readonly SupplierVariantAutoMapper $variantMapper,
        private readonly SupplierDescriptionFormatter $descriptionFormatter,
    ) {
    }

    public function import(DropshipSupplierProduct $supplierProduct): ProductImportResult
    {
        return DB::transaction(function () use ($supplierProduct): ProductImportResult {
            $source = DropshipSupplierProduct::query()
                ->with('supplier')
                ->lockForUpdate()
                ->findOrFail($supplierProduct->getKey());

            $link = $source->productLink()->with('product')->first();
            if ($link?->product) {
                $this->syncProductContent($source, $link->product);
                return new ProductImportResult(ProductImportResult::ALREADY_LINKED, $link->product);
            }

            if ($source->currency !== 'BDT') {
                return new ProductImportResult(ProductImportResult::BLOCKED, reason: 'unsupported_currency');
            }
            if ($source->supplier_category_key === null || $source->supplier === null) {
                return new ProductImportResult(ProductImportResult::BLOCKED, reason: 'missing_category_mapping');
            }

            $category = $this->categoryMapper->localCategoryFor($source->supplier, $source->supplier_category_key);
            if ($category === null) {
                return new ProductImportResult(ProductImportResult::BLOCKED, reason: 'missing_category_mapping');
            }

            $price = $this->pricingEngine->calculate(
                $source->cost_price,
                $source->max_price,
                $source->supplier->pricing_rules ?? [],
            );

            $product = Product::create([
                'category_id' => $category->getKey(),
                'name' => Str::limit(trim($source->name), 255, ''),
                'sku' => $source->product_code ?: $source->supplier_product_id,
                'description' => $this->descriptionFormatter->format($source),
                'regular_price' => $price->regularSellingPrice ?? $price->finalPrice ?? $source->max_price ?? $source->cost_price ?? 0,
                'sale_price' => $this->salePrice($price),
                'stock_quantity' => $this->stockQuantity($source),
                'is_published' => false,
            ]);

            if ($link === null) {
                $link = new DropshipProductLink([
                    'supplier_product_row_id' => $source->getKey(),
                ]);
            }
            $link->fill([
                'product_id' => $product->getKey(),
                'sync_status' => 'active',
                'product_created_by_integration' => true,
                // Initial import populates the product. Later sync is restricted
                // to supplier-owned price and stock fields until an admin opts in.
                'field_sync_rules' => [
                    'price' => true,
                    'stock' => true,
                    'name' => false,
                    'sku' => false,
                    'category' => false,
                    'images' => false,
                ],
                'pricing_snapshot' => $price->toArray(),
                'last_data_synced_at' => now(),
                'last_price_synced_at' => now(),
                'last_stock_synced_at' => now(),
            ]);
            $link->save();
            $this->syncProductContent($source, $product);
            $this->variantMapper->sync($source->load('variants', 'productLink'));

            return new ProductImportResult(ProductImportResult::IMPORTED, $product);
        });
    }

    private function stockQuantity(DropshipSupplierProduct $source): int
    {
        if ($source->is_available === false || $source->stock_qty === null) {
            return 0;
        }

        return min(4_294_967_295, max(0, (int) floor((float) $source->stock_qty)));
    }

    private function salePrice(PriceBreakdown $price): ?float
    {
        return $price->finalSalePrice !== null
            && $price->regularSellingPrice !== null
            && $price->finalSalePrice < $price->regularSellingPrice
            ? $price->finalSalePrice
            : null;
    }

    private function syncProductContent(DropshipSupplierProduct $source, Product $product): void
    {
        $payload = is_array($source->raw_payload) ? $source->raw_payload : [];
        $updates = [];
        $sourceSku = trim((string) ($source->product_code ?: $source->supplier_product_id));
        if ($sourceSku !== '' && $product->sku !== $sourceSku) {
            $updates['sku'] = $sourceSku;
        }
        $description = $this->descriptionFormatter->format($source);
        if ($description && $product->description !== $description) {
            $updates['description'] = $description;
        }
        if ($updates !== []) {
            $product->forceFill($updates)->save();
        }

        $images = $this->supplierImages($payload);
        foreach ($images as $position => $image) {
            ProductImage::query()->firstOrCreate(
                ['product_id' => $product->getKey(), 'path' => $image],
                ['alt' => $product->name, 'is_primary' => $position === 0, 'position' => $position],
            );
        }
    }

    /** @return list<string> */
    private function supplierImages(array $payload): array
    {
        $images = [];
        foreach (['thumbnail_img'] as $field) {
            if (is_string($payload[$field] ?? null) && trim($payload[$field]) !== '') $images[] = trim($payload[$field]);
        }
        foreach (($payload['product_images'] ?? []) as $item) {
            $image = is_array($item) ? ($item['product_image'] ?? null) : null;
            if (is_string($image) && trim($image) !== '') $images[] = trim($image);
        }

        return array_values(array_unique($images));
    }
}

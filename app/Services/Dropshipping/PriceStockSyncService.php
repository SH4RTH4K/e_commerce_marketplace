<?php

namespace App\Services\Dropshipping;

use App\Models\DropshipSupplierProduct;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class PriceStockSyncService
{
    public function __construct(
        private readonly PricingEngine $pricingEngine,
        private readonly SupplierDescriptionFormatter $descriptionFormatter,
    )
    {
    }

    public function sync(DropshipSupplierProduct $supplierProduct): PriceStockSyncResult
    {
        return DB::transaction(function () use ($supplierProduct): PriceStockSyncResult {
            $source = DropshipSupplierProduct::query()
                ->with('supplier')
                ->lockForUpdate()
                ->findOrFail($supplierProduct->getKey());
            $link = $source->productLink()->lockForUpdate()->first();

            if ($link === null || $link->product_id === null || $link->sync_status !== 'active') {
                return new PriceStockSyncResult(PriceStockSyncResult::SKIPPED);
            }

            $product = Product::query()->lockForUpdate()->find($link->product_id);
            if ($product === null) {
                return new PriceStockSyncResult(PriceStockSyncResult::SKIPPED);
            }

            $rules = $link->field_sync_rules ?? [];
            $warnings = [];
            $priceUpdated = false;
            $stockUpdated = false;
            $descriptionUpdated = false;
            $linkUpdates = [];
            $productUpdates = [];
            $now = now();

            $description = $this->descriptionFormatter->format($source);
            if ($description !== null && $product->description !== $description) {
                $productUpdates['description'] = $description;
                $descriptionUpdated = true;
            }

            if (($rules['price'] ?? false) === true) {
                if ($source->currency !== 'BDT') {
                    $warnings[] = 'unsupported_currency';
                } else {
                    $price = $this->pricingEngine->calculate(
                        $source->cost_price,
                        $source->max_price,
                        $source->supplier?->pricing_rules ?? [],
                    );
                    if ($price->isValid()) {
                        $productUpdates['regular_price'] = $price->regularSellingPrice ?? $price->finalPrice;
                        $productUpdates['sale_price'] = $this->salePrice($price);
                        $linkUpdates['last_price_synced_at'] = $now;
                        $linkUpdates['pricing_snapshot'] = $price->toArray();
                        $warnings = array_merge($warnings, $price->warnings);
                        $priceUpdated = true;
                    } else {
                        // Keep the latest breakdown so the administrator can
                        // see why the existing storefront price was protected.
                        $linkUpdates['pricing_snapshot'] = $price->toArray();
                        $warnings[] = 'pricing_' . $price->reason;
                    }
                }
            }

            if (($rules['stock'] ?? false) === true) {
                if ($source->stock_qty === null && $source->is_available !== false) {
                    $warnings[] = 'unknown_stock_not_updated';
                } else {
                    $productUpdates['stock_quantity'] = $this->stockQuantity($source);
                    $linkUpdates['last_stock_synced_at'] = $now;
                    $stockUpdated = true;
                }
            }

            if ($productUpdates !== []) {
                // Deliberately does not touch name, SKU, category, images,
                // variants, or is_published.
                $product->update($productUpdates);
            }
            if ($linkUpdates !== []) {
                $link->update($linkUpdates);
            }

            $status = $priceUpdated || $stockUpdated || $descriptionUpdated
                ? ($warnings === [] ? PriceStockSyncResult::UPDATED : PriceStockSyncResult::PARTIAL)
                : PriceStockSyncResult::SKIPPED;

            return new PriceStockSyncResult($status, $product->fresh(), $priceUpdated, $stockUpdated, $warnings, $descriptionUpdated);
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
}

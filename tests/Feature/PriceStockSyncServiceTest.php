<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Services\Dropshipping\CategoryMapper;
use App\Services\Dropshipping\PriceStockSyncResult;
use App\Services\Dropshipping\PriceStockSyncService;
use App\Services\Dropshipping\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceStockSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_and_stock_sync_preserves_draft_and_merchant_owned_fields(): void
    {
        [$source, $product] = $this->importedProduct();
        $product->update(['name' => 'Merchant title', 'is_published' => false]);
        $source->update([
            'cost_price' => 110,
            'max_price' => 170,
            'stock_qty' => 4,
            'raw_payload' => ['description' => "Supplier line one\nSupplier line two"],
        ]);

        $result = app(PriceStockSyncService::class)->sync($source);

        $this->assertSame(PriceStockSyncResult::UPDATED, $result->status);
        $this->assertTrue($result->priceUpdated);
        $this->assertTrue($result->stockUpdated);
        $this->assertSame('Merchant title', $product->fresh()->name);
        $this->assertFalse($product->fresh()->is_published);
        $this->assertSame(132.0, (float) $product->fresh()->regular_price);
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertTrue($result->descriptionUpdated);
        $this->assertSame('<p>Supplier line one<br />Supplier line two</p>', $product->fresh()->description);
    }

    public function test_unknown_stock_is_not_replaced_with_zero_during_sync(): void
    {
        [$source, $product] = $this->importedProduct();
        $source->update(['stock_qty' => null, 'is_available' => null]);

        $result = app(PriceStockSyncService::class)->sync($source);

        $this->assertTrue($result->priceUpdated);
        $this->assertFalse($result->stockUpdated);
        $this->assertContains('unknown_stock_not_updated', $result->warnings);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    /** @return array{DropshipSupplierProduct, \App\Models\Product} */
    private function importedProduct(): array
    {
        $supplier = DropshipSupplier::create([
            'key' => 'supplier-' . uniqid(),
            'name' => 'Supplier',
            'driver_key' => 'fake',
            'base_url' => 'https://supplier.example',
            'pricing_rules' => ['selling_markup' => ['type' => 'percent', 'value' => 20]],
        ]);
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics-' . uniqid()]);
        app(CategoryMapper::class)->mapManually($supplier, 'electronics', $category);
        $source = DropshipSupplierProduct::create([
            'supplier_id' => $supplier->id,
            'supplier_product_id' => 'source-' . uniqid(),
            'supplier_category_key' => 'electronics',
            'name' => 'Supplier Product',
            'currency' => 'BDT',
            'cost_price' => 100,
            'max_price' => 150,
            'stock_qty' => 8,
            'is_available' => true,
            'raw_payload' => [],
            'fetched_at' => now(),
        ]);

        $import = app(ProductImportService::class)->import($source);

        return [$source, $import->product];
    }
}

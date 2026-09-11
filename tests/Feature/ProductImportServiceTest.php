<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\Product;
use App\Services\Dropshipping\CategoryMapper;
use App\Services\Dropshipping\ProductImportResult;
use App\Services\Dropshipping\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_a_mapped_product_as_a_draft_and_is_idempotent(): void
    {
        [$supplier, $source] = $this->supplierAndSource();
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        app(CategoryMapper::class)->mapManually($supplier, 'electronics', $category);

        $service = app(ProductImportService::class);
        $first = $service->import($source);
        $second = $service->import($source);

        $this->assertSame(ProductImportResult::IMPORTED, $first->status);
        $this->assertFalse($first->product->is_published);
        $this->assertSame(120.0, (float) $first->product->regular_price);
        $this->assertSame(8, $first->product->stock_quantity);
        $this->assertSame(ProductImportResult::ALREADY_LINKED, $second->status);
        $this->assertSame($first->product->id, $second->product->id);
        $this->assertSame(1, Product::count());
        $this->assertSame(['price' => true, 'stock' => true, 'name' => false, 'sku' => false, 'category' => false, 'images' => false], $first->product->fresh()->supplierLinks()->first()->field_sync_rules);
    }

    public function test_it_blocks_an_unmapped_or_unpriceable_supplier_product(): void
    {
        [, $unmapped] = $this->supplierAndSource();

        $result = app(ProductImportService::class)->import($unmapped);

        $this->assertSame(ProductImportResult::BLOCKED, $result->status);
        $this->assertSame('missing_category_mapping', $result->reason);
        $this->assertSame(0, Product::count());
    }

    public function test_it_preserves_supplier_description_formatting_during_import_and_sync(): void
    {
        [$supplier, $source] = $this->supplierAndSource();
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        app(CategoryMapper::class)->mapManually($supplier, 'electronics', $category);

        $source->update([
            'raw_payload' => [
                'description' => "First supplier line\nSecond supplier line",
            ],
        ]);

        $service = app(ProductImportService::class);
        $product = $service->import($source)->product;

        $this->assertSame('<p>First supplier line<br />Second supplier line</p>', $product->description);

        $source->update([
            'raw_payload' => [
                'details' => '&lt;p&gt;&lt;strong&gt;Updated supplier details&lt;/strong&gt;&lt;/p&gt;',
            ],
        ]);
        $service->import($source);

        $this->assertSame('<p><strong>Updated supplier details</strong></p>', $product->fresh()->description);
    }

    /** @return array{DropshipSupplier, DropshipSupplierProduct} */
    private function supplierAndSource(): array
    {
        $supplier = DropshipSupplier::create([
            'key' => 'supplier-' . uniqid(),
            'name' => 'Supplier',
            'driver_key' => 'fake',
            'base_url' => 'https://supplier.example',
            'pricing_rules' => [
                'minimum_markup' => ['type' => 'percent', 'value' => 10],
                'selling_markup' => ['type' => 'percent', 'value' => 20],
            ],
        ]);
        $source = DropshipSupplierProduct::create([
            'supplier_id' => $supplier->id,
            'supplier_product_id' => 'source-' . uniqid(),
            'supplier_category_key' => 'electronics',
            'name' => 'Supplier Product',
            'product_code' => 'SUP-100',
            'currency' => 'BDT',
            'cost_price' => 100,
            'max_price' => 150,
            'stock_qty' => 8,
            'is_available' => true,
            'raw_payload' => [],
            'fetched_at' => now(),
        ]);

        return [$supplier, $source];
    }
}

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

    public function test_it_formats_joined_supplier_fields_during_import(): void
    {
        [$supplier, $source] = $this->supplierAndSource();
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        app(CategoryMapper::class)->mapManually($supplier, 'electronics', $category);

        $source->update([
            'raw_payload' => [
                'description' => 'Product details of Test WatchBrand: OLEVSModel Number: 9931GMovement brand: QuartzDial diameter: 41mmCase shape: Round',
            ],
        ]);

        $product = app(ProductImportService::class)->import($source)->product;

        $this->assertSame(
            '<p>Product details of Test Watch</p><p><strong>Brand:</strong> OLEVS</p><p><strong>Model Number:</strong> 9931G</p><p><strong>Movement brand:</strong> Quartz</p><p><strong>Dial diameter:</strong> 41mm</p><p><strong>Case shape:</strong> Round</p>',
            $product->description,
        );
    }

    public function test_it_formats_joined_apparel_description_fields_during_import(): void
    {
        [$supplier, $source] = $this->supplierAndSource();
        $category = Category::create(['name' => 'Fashion', 'slug' => 'fashion']);
        app(CategoryMapper::class)->mapManually($supplier, 'electronics', $category);

        $source->update([
            'raw_payload' => [
                'description' => "Gabardine PantMain Material: TwillStretch: StretchableWash & Care: Machine WashWaist: Mid-riseQuality: 98% Cotton 2% SpandexButtery smooth chinosPocket: 2 Side pocketGender: MenMeasurementSize: 32, Waist: 32, Length: 41",
            ],
        ]);

        $product = app(ProductImportService::class)->import($source)->product;

        $this->assertStringContainsString('<p><strong>Main Material:</strong> Twill</p>', $product->description);
        $this->assertStringContainsString('<p><strong>Wash &amp; Care:</strong> Machine Wash</p>', $product->description);
        $this->assertStringContainsString('<p><strong>Quality:</strong> 98% Cotton 2% Spandex</p>', $product->description);
        $this->assertStringContainsString('<p>Buttery smooth chinos</p>', $product->description);
        $this->assertStringContainsString('<p><strong>Size:</strong> 32, Waist: 32, Length: 41</p>', $product->description);
    }

    public function test_it_formats_joined_shirt_details_and_size_measurements_during_import(): void
    {
        [$supplier, $source] = $this->supplierAndSource();
        $category = Category::create(['name' => 'Fashion', 'slug' => 'fashion']);
        app(CategoryMapper::class)->mapManually($supplier, 'electronics', $category);

        $source->update([
            'raw_payload' => [
                'description' => 'Products detailsProduct Name: Cotton Collar ShirtFabrics: cottonSize Measurement: M = length 28", chest 38"L= length 29", chest 40"',
            ],
        ]);

        $product = app(ProductImportService::class)->import($source)->product;

        $this->assertStringContainsString('<p><strong>Product Name:</strong> Cotton Collar Shirt</p>', $product->description);
        $this->assertStringContainsString('<p><strong>Fabrics:</strong> cotton</p>', $product->description);
        $this->assertStringContainsString('<p><strong>Size Measurement:</strong> M = length 28&quot;, chest 38&quot;</p>', $product->description);
        $this->assertStringContainsString('<p>L= length 29&quot;, chest 40&quot;</p>', $product->description);
    }

    public function test_it_formats_joined_service_benefits_during_import(): void
    {
        [$supplier, $source] = $this->supplierAndSource();
        $category = Category::create(['name' => 'Jewellery', 'slug' => 'jewellery']);
        app(CategoryMapper::class)->mapManually($supplier, 'electronics', $category);

        $source->update([
            'raw_payload' => [
                'description' => '100% Authentic Satisfied Product100% Money Back Refund Policy10 Days Easy Return & Replace Policy1 Year Service WarrantySafe Online Payment & COD AvailableQuick Priority Support 24/7 DaysFastest Home Delivery For All orders',
            ],
        ]);

        $product = app(ProductImportService::class)->import($source)->product;

        $this->assertSame(
            '<p>100% Authentic Satisfied Product</p><p>100% Money Back Refund Policy</p><p>10 Days Easy Return &amp; Replace Policy</p><p>1 Year Service Warranty</p><p>Safe Online Payment &amp; COD Available</p><p>Quick Priority Support 24/7 Days</p><p>Fastest Home Delivery For All orders</p>',
            $product->description,
        );
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

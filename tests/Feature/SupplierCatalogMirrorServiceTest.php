<?php

namespace Tests\Feature;

use App\Models\DropshipSupplier;
use App\Services\Dropshipping\DTO\SupplierCategory;
use App\Services\Dropshipping\DTO\SupplierProduct;
use App\Services\Dropshipping\DTO\SupplierVariant;
use App\Services\Dropshipping\SupplierCatalogMirrorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierCatalogMirrorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_products_and_variants_are_mirrored_idempotently(): void
    {
        $supplier = DropshipSupplier::create([
            'key' => 'supplier',
            'name' => 'Supplier',
            'driver_key' => 'fake',
            'base_url' => 'https://supplier.example',
        ]);
        $service = app(SupplierCatalogMirrorService::class);

        $service->mirrorCategory($supplier, new SupplierCategory('electronics', 'Electronics', rawPayload: ['name' => 'Electronics']));
        $first = $service->mirrorProduct($supplier, $this->product('Product One', 8));
        $second = $service->mirrorProduct($supplier, $this->product('Renamed Product', 4));

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('dropship_supplier_categories', 1);
        $this->assertDatabaseCount('dropship_supplier_products', 1);
        $this->assertDatabaseCount('dropship_supplier_variants', 1);
        $this->assertDatabaseHas('dropship_supplier_products', [
            'id' => $first->id,
            'name' => 'Renamed Product',
            'stock_qty' => 4,
        ]);
    }

    public function test_normalized_description_is_kept_in_the_supplier_payload(): void
    {
        $supplier = DropshipSupplier::create([
            'key' => 'supplier-description',
            'name' => 'Supplier',
            'driver_key' => 'fake',
            'base_url' => 'https://supplier.example',
        ]);

        $mirrored = app(SupplierCatalogMirrorService::class)->mirrorProduct(
            $supplier,
            new SupplierProduct(
                id: 'product-description',
                name: 'Product with description',
                currency: 'BDT',
                description: '<p>Supplier <strong>formatted</strong> text</p>',
                rawPayload: ['id' => 'product-description'],
            ),
        );

        $this->assertSame('<p>Supplier <strong>formatted</strong> text</p>', $mirrored->raw_payload['description']);
    }

    private function product(string $name, int $stock): SupplierProduct
    {
        return new SupplierProduct(
            id: 'product-1',
            name: $name,
            currency: 'BDT',
            categoryKey: 'electronics',
            costPrice: 100,
            maxPrice: 150,
            stockQuantity: $stock,
            isAvailable: true,
            variants: [new SupplierVariant(
                id: 'variant-1',
                attributes: ['Color' => 'Black'],
                costPrice: 100,
                maxPrice: 150,
                stockQuantity: $stock,
                rawPayload: ['id' => 'variant-1'],
            )],
            rawPayload: ['id' => 'product-1', 'name' => $name],
        );
    }
}

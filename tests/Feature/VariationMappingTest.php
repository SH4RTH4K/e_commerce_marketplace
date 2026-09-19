<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\DropshipSupplierVariant;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VariationMappingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function variation_filters_distinguish_ready_records_from_products_waiting_for_import(): void
    {
        config(['dropshipping.enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $waitingProduct = $this->supplierProduct($supplier, 'waiting', 'Waiting Product');
        $waitingVariant = $this->supplierVariant($waitingProduct, 'waiting-red', 'Color', 'Red');

        $readyProduct = $this->supplierProduct($supplier, 'ready', 'Ready Product');
        $localProduct = $this->localProduct('Ready Product');
        DropshipProductLink::create([
            'supplier_product_row_id' => $readyProduct->id,
            'product_id' => $localProduct->id,
        ]);
        $readyVariant = $this->supplierVariant($readyProduct, 'ready-blue', 'Color', 'Blue');

        $this->actingAs($admin)->get('/admin/dropshipping/variations?status=ready')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.status', 'ready')
                ->has('variants', 1)
                ->where('variants.0.id', $readyVariant->id)
                ->where('variants.0.mapping_status', 'ready')
                ->where('counts.ready', 1)
                ->where('counts.awaiting_import', 1));

        $this->actingAs($admin)->get('/admin/dropshipping/variations?status=awaiting_import')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('variants', 1)
                ->where('variants.0.id', $waitingVariant->id)
                ->where('variants.0.mapping_status', 'awaiting_import'));
    }

    #[Test]
    public function ready_variations_can_be_mapped_automatically(): void
    {
        config(['dropshipping.enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $supplierProduct = $this->supplierProduct($supplier, 'speaker', 'Portable Speaker');
        $localProduct = $this->localProduct('Portable Speaker');
        DropshipProductLink::create([
            'supplier_product_row_id' => $supplierProduct->id,
            'product_id' => $localProduct->id,
        ]);
        $supplierVariant = $this->supplierVariant($supplierProduct, 'speaker-black', 'Color', 'Black');

        $this->actingAs($admin)->post('/admin/dropshipping/variations/auto-map', [
            'supplier_id' => $supplier->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $localProduct->id,
            'type' => 'Color',
            'value' => 'Black',
        ]);
        $this->assertDatabaseHas('dropship_variant_links', [
            'supplier_variant_row_id' => $supplierVariant->id,
        ]);
    }

    private function supplier(): DropshipSupplier
    {
        return DropshipSupplier::create([
            'key' => 'test-supplier',
            'name' => 'Test Supplier',
            'driver_key' => 'test',
            'base_url' => 'https://supplier.example',
            'is_active' => true,
        ]);
    }

    private function supplierProduct(DropshipSupplier $supplier, string $id, string $name): DropshipSupplierProduct
    {
        return DropshipSupplierProduct::create([
            'supplier_id' => $supplier->id,
            'supplier_product_id' => $id,
            'name' => $name,
            'raw_payload' => [],
            'fetched_at' => now(),
        ]);
    }

    private function supplierVariant(DropshipSupplierProduct $product, string $id, string $type, string $value): DropshipSupplierVariant
    {
        return DropshipSupplierVariant::create([
            'supplier_product_row_id' => $product->id,
            'supplier_variant_id' => $id,
            'attributes' => ['type' => $type, 'value' => $value],
            'stock_qty' => 5,
            'is_available' => true,
        ]);
    }

    private function localProduct(string $name): Product
    {
        $category = Category::create([
            'name' => $name . ' Category',
            'slug' => str($name)->slug() . '-category',
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug(),
        ]);
    }
}

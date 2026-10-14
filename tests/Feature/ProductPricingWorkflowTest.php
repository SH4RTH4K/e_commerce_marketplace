<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\Dropshipping\CategoryMapper;
use App\Services\Dropshipping\PriceStockSyncService;
use App\Services\Dropshipping\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductPricingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function importedProduct(): array
    {
        $supplier = DropshipSupplier::create([
            'key' => 'workflow-supplier', 'name' => 'Workflow Supplier', 'driver_key' => 'fake', 'base_url' => 'https://supplier.example',
            'pricing_rules' => [
                'selling' => ['base' => 'supplier_cost', 'adjustment_type' => 'none', 'adjustment_value' => 0],
                'minimum' => ['base' => 'supplier_cost', 'adjustment_type' => 'plus_percent', 'adjustment_value' => 20, 'cap_enabled' => true, 'rounding' => ['mode' => 'down', 'increment' => 5]],
                'maximum' => ['base' => 'supplier_maximum', 'adjustment_type' => 'none', 'adjustment_value' => 0, 'cap_enabled' => true],
                'regular_price_source' => 'maximum',
                'discount' => ['type' => 'percent', 'value' => 10, 'protect_minimum' => true],
            ],
        ]);
        $category = Category::create(['name' => 'Electronics', 'slug' => 'workflow-electronics']);
        app(CategoryMapper::class)->mapManually($supplier, 'electronics', $category);
        $source = DropshipSupplierProduct::create([
            'supplier_id' => $supplier->id, 'supplier_product_id' => 'source-1', 'supplier_category_key' => 'electronics',
            'name' => 'Workflow Product', 'currency' => 'BDT', 'cost_price' => 590, 'max_price' => 990,
            'stock_qty' => 8, 'is_available' => true, 'raw_payload' => [], 'fetched_at' => now(),
        ]);
        return [$source, app(ProductImportService::class)->import($source)->product];
    }

    private function payload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'name' => $product->name, 'category_id' => $product->category_id, 'regular_price' => 990, 'sale_price' => 850,
            'selling_price' => 800, 'minimum_price' => 705, 'maximum_price' => 990,
            'pricing_mode' => 'manual', 'enforce_minimum_price' => true, 'enforce_maximum_price' => true,
            'protect_minimum_price' => true, 'stock_quantity' => $product->stock_quantity,
        ], $overrides);
    }

    public function test_import_and_editor_expose_distinct_selling_minimum_maximum_regular_and_discounted_prices(): void
    {
        [, $product] = $this->importedProduct();
        $this->assertSame('705.00', $product->selling_price);
        $this->assertSame('705.00', $product->minimum_price);
        $this->assertSame('990.00', $product->maximum_price);
        $this->assertSame('990.00', $product->regular_price);
        $this->assertSame('891.00', $product->sale_price);
        $this->assertArrayNotHasKey('minimum_price', $product->toArray());
        $this->assertArrayNotHasKey('maximum_price', $product->toArray());
        $this->get("/admin/products/{$product->id}/edit")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Products/Form')->where('pricing.mode', 'auto')
            ->where('product.minimum_price', '705.00')->where('product.maximum_price', '990.00')
            ->where('pricing.raw_selling', 590)->where('pricing.regular_source', 'maximum')
            ->where('pricing.suggested.selling_price', 705)->where('pricing.suggested.sale_price', 891));
    }

    public function test_manual_product_prices_survive_supplier_sync_while_stock_still_updates(): void
    {
        [$source, $product] = $this->importedProduct();
        $this->put("/admin/products/{$product->id}", $this->payload($product))->assertSessionHasNoErrors();
        $source->update(['cost_price' => 650, 'max_price' => 1100, 'stock_qty' => 3]);
        $result = app(PriceStockSyncService::class)->sync($source);
        $product->refresh();
        $this->assertFalse($result->priceUpdated);
        $this->assertTrue($result->stockUpdated);
        $this->assertSame(3, $product->stock_quantity);
        $this->assertSame('manual', $product->pricing_mode);
        $this->assertSame('800.00', $product->selling_price);
        $this->assertSame('705.00', $product->minimum_price);
        $this->assertSame('990.00', $product->maximum_price);
        $this->assertSame('850.00', $product->sale_price);
        $this->assertSame('990.00', $product->regular_price);
    }

    public function test_returning_to_supplier_rules_recalculates_and_removes_legacy_override(): void
    {
        [$source, $product] = $this->importedProduct();
        $this->put("/admin/products/{$product->id}", $this->payload($product))->assertSessionHasNoErrors();
        $source->productLink->update(['price_override' => ['result_regular_price' => 990, 'result_sale_price' => 850]]);
        $source->update(['cost_price' => 650, 'max_price' => 1100]);
        $this->put("/admin/products/{$product->id}", $this->payload($product, ['pricing_mode' => 'auto']))->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame('auto', $product->pricing_mode);
        $this->assertSame('780.00', $product->selling_price);
        $this->assertSame('1100.00', $product->regular_price);
        $this->assertSame('990.00', $product->sale_price);
        $this->assertNull($source->productLink->fresh()->price_override);
        $source->update(['max_price' => 1200]);
        $this->assertTrue(app(PriceStockSyncService::class)->sync($source)->priceUpdated);
        $this->assertSame('1080.00', $product->fresh()->sale_price);
    }

    #[DataProvider('conflicts')]
    public function test_conflicting_manual_prices_are_rejected_without_modifying_product(array $values, string $field): void
    {
        [, $product] = $this->importedProduct();
        $this->putJson("/admin/products/{$product->id}", $this->payload($product, $values))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame('auto', $product->fresh()->pricing_mode);
        $this->assertSame('891.00', $product->fresh()->sale_price);
    }

    public static function conflicts(): array
    {
        return [
            [['minimum_price' => 1000], 'minimum_price'],
            [['selling_price' => 700], 'selling_price'],
            [['selling_price' => 1000], 'selling_price'],
            [['sale_price' => 700], 'sale_price'],
            [['sale_price' => 1000], 'sale_price'],
            [['minimum_price' => null], 'minimum_price'],
            [['selling_price' => -1], 'selling_price'],
            [['selling_price' => 800.001], 'selling_price'],
        ];
    }

    public function test_caps_and_discount_protection_can_be_disabled_and_optional_limits_cleared(): void
    {
        [, $product] = $this->importedProduct();
        $this->put("/admin/products/{$product->id}", $this->payload($product, [
            'selling_price' => 600, 'sale_price' => 590, 'minimum_price' => null, 'maximum_price' => null,
            'enforce_minimum_price' => false, 'enforce_maximum_price' => false,
        ]))->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->minimum_price);
        $this->assertNull($product->fresh()->maximum_price);
        $this->assertSame('590.00', $product->fresh()->sale_price);
    }

    public function test_legacy_bulk_override_is_preserved_during_sync(): void
    {
        [$source, $product] = $this->importedProduct();
        $product->update(['sale_price' => 850]);
        $source->productLink->update(['price_override' => ['result_sale_price' => 850]]);
        $source->update(['max_price' => 1200]);
        $this->assertFalse(app(PriceStockSyncService::class)->sync($source)->priceUpdated);
        $this->assertSame('850.00', $product->fresh()->sale_price);
    }

    public function test_unlinked_product_can_save_the_new_fields_without_supplier_rules(): void
    {
        $category = Category::create(['name' => 'Manual', 'slug' => 'manual']);
        $this->post('/admin/products', [
            'name' => 'Manual Product', 'category_id' => $category->id, 'pricing_mode' => 'manual',
            'selling_price' => 200, 'regular_price' => 250, 'sale_price' => 220,
            'minimum_price' => 180, 'maximum_price' => 300, 'enforce_minimum_price' => true,
            'enforce_maximum_price' => true, 'protect_minimum_price' => true,
        ])->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertSame('200.00', $product->selling_price);
        $this->assertSame('220.00', $product->sale_price);
        $this->assertSame('manual', $product->pricing_mode);
    }

    public function test_customers_cannot_edit_prices(): void
    {
        [, $product] = $this->importedProduct();
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->put("/admin/products/{$product->id}", $this->payload($product))->assertRedirect(route('admin.login'));
        $this->assertSame('891.00', $product->fresh()->sale_price);
    }
}

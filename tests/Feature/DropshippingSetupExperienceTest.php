<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\Dropshipping\SupplierRegistry;
use App\Services\Dropshipping\Suppliers\ConfigurableRestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DropshippingSetupExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['dropshipping.enabled' => true]);
        app()->instance(SupplierRegistry::class, new SupplierRegistry([new ConfigurableRestClient('mohasagor')]));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function supplier(string $key, string $name): DropshipSupplier
    {
        return DropshipSupplier::create(['key' => $key, 'name' => $name, 'supplier_type' => 'dropshipping',
            'driver_key' => 'mohasagor', 'is_active' => true, 'base_url' => 'https://supplier.example',
            'api_key' => 'private-api-key', 'secret_key' => 'private-secret', 'last_connection_status' => 'success']);
    }

    private function source(DropshipSupplier $supplier, string $externalId, bool $import = true, bool $published = false, bool $owned = true): DropshipSupplierProduct
    {
        $source = DropshipSupplierProduct::create(['supplier_id' => $supplier->id, 'supplier_product_id' => $externalId,
            'name' => 'Shared product '.$externalId, 'supplier_category_key' => $supplier->key, 'currency' => 'BDT',
            'cost_price' => 100, 'max_price' => 200, 'raw_payload' => [], 'fetched_at' => now()]);
        if ($import) {
            $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);
            $product = Product::create(['category_id' => $category->id, 'supplier_id' => $supplier->id,
                'source_type' => $owned ? 'dropshipping' : 'manual', 'name' => $source->name, 'regular_price' => 150, 'is_published' => $published]);
            DropshipProductLink::create(['supplier_product_row_id' => $source->id, 'product_id' => $product->id,
                'product_created_by_integration' => $owned, 'sync_status' => 'active']);
        }
        return $source;
    }

    public function test_dashboard_reuses_existing_supplier_and_counts_catalog_imports_and_published_separately(): void
    {
        $supplier = $this->supplier('main-supplier', 'Mohasagor Bangladesh');
        $this->source($supplier, 'external-1', published: true);
        $this->source($supplier, 'external-2');
        $this->source($supplier, 'external-3', import: false);
        $this->source($supplier, 'manual-link', published: true, owned: false);
        $other = $this->supplier('another', 'Another provider');
        $this->source($other, 'other', published: true);

        $this->get('/admin/dropshipping')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selected_supplier_id', $supplier->id)->where('suppliers.1.name', $supplier->name)
            ->where('suppliers.1.products_count', 4)->where('suppliers.1.imported_products_count', 2)
            ->where('suppliers.1.published_products_count', 1)->missing('suppliers.1.api_key')->missing('suppliers.1.secret_key'));
        $this->get('/admin/dropshipping?supplier_id='.$other->id)->assertInertia(fn (Assert $page) => $page->where('selected_supplier_id', $other->id));
        $this->assertDatabaseCount('dropship_suppliers', 2);
        $this->assertDatabaseCount('products', 4);
    }

    public function test_supplier_filter_scopes_both_catalog_and_store_lists_and_explicit_all_keeps_all_results(): void
    {
        $supplier = $this->supplier('main-supplier', 'Mohasagor Bangladesh');
        $source = $this->source($supplier, 'main-1');
        $other = $this->supplier('another', 'Another provider');
        $this->source($other, 'other-1');
        $this->get('/admin/dropshipping/products?supplier_id='.$supplier->id.'&q=Shared')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selected_supplier_id', $supplier->id)->where('pagination.total', 1)->where('products.0.id', $source->id)
            ->where('categories', [$supplier->key]));
        $this->get('/admin/dropshipping/imported?supplier_id='.$supplier->id.'&q=Shared')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selected_supplier_id', $supplier->id)->where('pagination.total', 1)->where('products.0.supplier', $supplier->name)
            ->has('sync_suppliers', 1)->where('sync_suppliers.0.id', $supplier->id)->where('categories', [$supplier->key]));
        foreach (['products', 'imported'] as $path) {
            $this->get('/admin/dropshipping/'.$path.'?supplier_id=0')->assertInertia(fn (Assert $page) => $page->where('selected_supplier_id', null)->where('pagination.total', 2));
            $this->get('/admin/dropshipping/'.$path.'?supplier_id=999')->assertSessionHasErrors('supplier_id');
        }
    }

    public function test_settings_select_saved_supplier_without_revealing_credentials_or_creating_profiles(): void
    {
        $supplier = $this->supplier('main-supplier', 'Mohasagor Bangladesh');
        $this->get('/admin/dropshipping/settings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selected_supplier_id', $supplier->id)->where('suppliers.0.name', $supplier->name)
            ->where('suppliers.0.has_api_key', true)->where('suppliers.0.has_secret_key', true)
            ->missing('suppliers.0.api_key')->missing('suppliers.0.secret_key')->has('driver_profiles', 0));
        $this->assertDatabaseCount('dropship_suppliers', 1);
        $this->assertDatabaseCount('dropship_driver_profiles', 0);
        $this->assertSame('private-api-key', $supplier->fresh()->api_key);
    }

    public function test_pricing_retains_supplier_scope_and_status_changes_are_audited(): void
    {
        $supplier = $this->supplier('main-supplier', 'Mohasagor Bangladesh');
        $this->supplier('another', 'Another provider');
        $this->get('/admin/dropshipping/pricing?supplier_id='.$supplier->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selected_supplier_id', $supplier->id)->has('suppliers', 1)->where('suppliers.0.id', $supplier->id));
        $this->patch('/admin/dropshipping/suppliers/'.$supplier->id.'/toggle')->assertSessionHasNoErrors();
        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertDatabaseHas('supplier_audits', ['supplier_id' => $supplier->id, 'event' => 'deactivated', 'actor_id' => auth()->id()]);
        $this->assertSame('private-api-key', $supplier->fresh()->api_key);
    }

    public function test_ordinary_connection_edit_preserves_saved_credentials_and_unspecified_category_settings(): void
    {
        $supplier = $this->supplier('main-supplier', 'Mohasagor Bangladesh');
        $supplier->update(['sync_rules' => ['category_endpoint' => 'existing-category-path', 'import_images' => true, 'import_variations' => true]]);
        $this->patch('/admin/dropshipping/suppliers/'.$supplier->id, ['name' => $supplier->name, 'driver_key' => $supplier->driver_key,
            'base_url' => $supplier->base_url, 'api_key' => '', 'secret_key' => '', 'import_images' => 0])->assertSessionHasNoErrors();
        $supplier->refresh();
        $this->assertSame('existing-category-path', $supplier->sync_rules['category_endpoint']);
        $this->assertFalse($supplier->sync_rules['import_images']);
        $this->assertTrue($supplier->sync_rules['import_variations']);
        $this->assertSame('private-api-key', $supplier->api_key);
        $this->assertSame('private-secret', $supplier->secret_key);
        $this->assertDatabaseCount('dropship_suppliers', 1);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipSupplier;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\SupplierAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['dropshipping.enabled' => false]);
    }

    private function staff(array $permissions = ['suppliers.manage']): User
    {
        $user = User::factory()->create(['role' => 'staff', 'permissions' => $permissions]);
        $this->actingAs($user);

        return $user;
    }

    private function supplier(array $attributes = []): DropshipSupplier
    {
        return DropshipSupplier::create([
            'key' => fake()->unique()->slug(), 'name' => 'ABC Electronics', 'supplier_type' => 'local',
            'driver_key' => 'manual', 'is_active' => true, ...$attributes,
        ]);
    }

    private function product(DropshipSupplier $supplier, string $source = 'manual'): Product
    {
        $category = Category::firstOrCreate(['slug' => 'supplier-test'], ['name' => 'Supplier test']);

        return Product::create(['name' => fake()->unique()->name(), 'category_id' => $category->id,
            'regular_price' => 100, 'stock_quantity' => 24, 'source_type' => $source, 'supplier_id' => $supplier->id]);
    }

    public function test_supplier_crud_is_independent_of_dropshipping_and_records_full_business_data_and_audits(): void
    {
        $actor = $this->staff();
        $this->get('/admin/suppliers')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Suppliers/Index')->where('canManage', true));
        $this->get('/admin/suppliers/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Suppliers/Form'));
        $data = ['name' => 'Local supplier', 'supplier_type' => 'wholesale', 'contact_person' => 'Amin',
            'mobile' => '01700000000', 'whatsapp' => '01800000000', 'email' => 'amin@example.com',
            'website' => 'https://example.com', 'business_address' => '10 Market Road', 'city' => 'Dhaka',
            'district' => 'Dhaka', 'division' => 'Dhaka', 'country' => 'Bangladesh', 'business_name' => 'Amin Trading',
            'trade_license' => 'TL-100', 'tax_vat_bin' => 'BIN-123', 'payment_terms' => '30 days', 'notes' => 'Contact before delivery',
            'api_key' => 'must-not-save', 'secret_key' => 'must-not-save', 'base_url' => 'https://must-not-save.example'];
        $this->post('/admin/suppliers', $data)->assertRedirect();
        $supplier = DropshipSupplier::where('name', 'Local supplier')->sole();
        $this->assertSame('SUP-'.str_pad((string) $supplier->id, 5, '0', STR_PAD_LEFT), $supplier->supplier_code);
        $this->assertSame($actor->id, $supplier->created_by);
        $this->assertNull($supplier->base_url);
        $this->assertNull($supplier->api_key);
        $this->get('/admin/suppliers/'.$supplier->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Suppliers/Show')->where('supplier.whatsapp', '01800000000')
            ->where('supplier.business_address', '10 Market Road')->where('supplier.trade_license', 'TL-100')
            ->where('supplier.tax_vat_bin', 'BIN-123')->where('supplier.payment_terms', '30 days')
            ->where('supplier.created_by', $actor->name)->missing('supplier.api_key')->missing('supplier.base_url'));
        $this->get('/admin/suppliers/'.$supplier->id.'/edit')->assertInertia(fn (Assert $page) => $page
            ->where('supplier.notes', 'Contact before delivery')->missing('supplier.sync_rules')->missing('supplier.secret_key'));
        $this->patch('/admin/suppliers/'.$supplier->id, [...$data, 'name' => 'Updated supplier'])->assertRedirect();
        $this->patch('/admin/suppliers/'.$supplier->id.'/toggle')->assertRedirect();
        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertDatabaseHas('supplier_audits', ['supplier_id' => $supplier->id, 'event' => 'created', 'actor_id' => $actor->id]);
        $this->assertDatabaseHas('supplier_audits', ['supplier_id' => $supplier->id, 'event' => 'updated']);
        $this->assertDatabaseHas('supplier_audits', ['supplier_id' => $supplier->id, 'event' => 'deactivated']);
        $this->assertStringNotContainsString('must-not-save', SupplierAudit::where('supplier_id', $supplier->id)->get()->toJson());
    }

    public function test_json_creation_returns_supplier_without_navigation_or_api_configuration(): void
    {
        $this->staff();
        $response = $this->postJson('/admin/suppliers', ['name' => 'Quick local', 'supplier_type' => 'local']);
        $response->assertCreated()->assertJsonPath('supplier.name', 'Quick local')->assertJsonPath('supplier.is_active', true);
        $this->assertArrayNotHasKey('api_key', $response->json('supplier'));
        $this->assertArrayNotHasKey('base_url', $response->json('supplier'));
        $this->assertSame('manual', DropshipSupplier::findOrFail($response->json('supplier.id'))->driver_key);
    }

    public function test_supplier_codes_are_validated_and_existing_api_settings_are_preserved_on_edit(): void
    {
        $this->staff();
        $existing = $this->supplier(['supplier_code' => 'CUSTOM-1']);
        $this->postJson('/admin/suppliers', ['name' => 'Duplicate code', 'supplier_type' => 'local', 'supplier_code' => 'custom-1'])
            ->assertUnprocessable()->assertJsonValidationErrors('supplier_code');
        $apiSupplier = $this->supplier(['supplier_type' => 'dropshipping', 'driver_key' => 'mohasagor',
            'base_url' => 'https://supplier.example', 'api_key' => 'existing-key', 'secret_key' => 'existing-secret', 'pricing_rules' => ['margin' => 10]]);
        $this->patch('/admin/suppliers/'.$apiSupplier->id, ['name' => 'Business name updated', 'supplier_type' => 'dropshipping',
            'driver_key' => 'other', 'base_url' => 'https://attacker.example', 'api_key' => 'bad', 'pricing_rules' => ['margin' => 99]])->assertRedirect();
        $apiSupplier->refresh();
        $this->assertSame('mohasagor', $apiSupplier->driver_key);
        $this->assertSame('existing-key', $apiSupplier->api_key);
        $this->assertSame(['margin' => 10], $apiSupplier->pricing_rules);
        $this->get('/admin/suppliers/'.$apiSupplier->id.'/edit')->assertInertia(fn (Assert $page) => $page
            ->where('supplier.integration', 'mohasagor Dropshipping')->missing('supplier.driver_key')->missing('supplier.pricing_rules'));
        $this->assertNotNull($existing->id);
    }

    public function test_view_permission_cannot_modify_suppliers_and_product_or_dropshipping_permission_is_not_enough(): void
    {
        $supplier = $this->supplier();
        $this->staff(['suppliers.view']);
        $this->get('/admin/suppliers')->assertOk();
        $this->get('/admin/suppliers/report')->assertForbidden();
        $this->staff(['suppliers.view', 'supplier_reports.view']);
        $this->get('/admin/suppliers/report')->assertOk();
        $this->post('/admin/suppliers', ['name' => 'Denied', 'supplier_type' => 'local'])->assertForbidden();
        $this->patch('/admin/suppliers/'.$supplier->id.'/toggle')->assertForbidden();
        $this->get('/admin/suppliers/'.$supplier->id.'/edit')->assertForbidden();
        $this->staff(['products.view', 'products.create', 'dropshipping.manage']);
        $this->get('/admin/suppliers')->assertForbidden();
        $this->get('/admin/suppliers/report?format=csv')->assertForbidden();
    }

    public function test_referenced_supplier_has_no_delete_endpoint_and_deactivation_preserves_products_and_inventory(): void
    {
        $this->staff();
        $supplier = $this->supplier();
        $product = $this->product($supplier);
        InventoryAdjustment::create(['supplier_id' => $supplier->id, 'product_id' => $product->id,
            'product_name' => $product->name, 'operation' => 'add', 'stock_before' => 4, 'stock_after' => 24,
            'change' => 20, 'reason' => 'Stock received']);
        $this->delete('/admin/suppliers/'.$supplier->id)->assertStatus(405);
        $this->patch('/admin/suppliers/'.$supplier->id.'/toggle')->assertRedirect();
        $this->assertSame($supplier->id, $product->fresh()->supplier_id);
        $this->assertSame(24, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_adjustments', ['supplier_id' => $supplier->id, 'change' => 20]);
        $this->assertDatabaseHas('dropship_suppliers', ['id' => $supplier->id, 'deleted_at' => null]);
    }

    public function test_details_show_real_counts_complete_paginated_history_and_independent_page_parameters(): void
    {
        $this->staff(['suppliers.view']);
        $supplier = $this->supplier();
        $product = $this->product($supplier);
        $this->product($supplier, 'dropshipping');
        $this->product($supplier, 'other');
        for ($i = 0; $i < 25; $i++) InventoryAdjustment::create(['supplier_id' => $supplier->id, 'product_id' => $product->id,
            'product_name' => $product->name, 'operation' => 'add', 'stock_before' => 0, 'stock_after' => 1, 'change' => 1, 'reason' => 'Receipt '.$i]);
        $this->get('/admin/suppliers/'.$supplier->id.'?history_page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('metrics.products', 3)->where('metrics.manual', 1)->where('metrics.dropshipping', 1)
            ->where('metrics.other', 1)->where('metrics.inventory_references', 25)
            ->where('history.current_page', 2)->has('history.data', 5)->where('products.current_page', 1));
    }

    public function test_supplier_search_type_status_pagination_and_supplier_report_csv_use_actual_records(): void
    {
        $this->staff(['suppliers.view', 'supplier_reports.view', 'supplier_reports.execute']);
        $supplier = $this->supplier(['supplier_code' => 'ABC-1', 'mobile' => '01712345678']);
        $this->product($supplier);
        $this->product($supplier, 'dropshipping');
        $this->supplier(['name' => 'Inactive wholesaler', 'supplier_type' => 'wholesale', 'is_active' => false]);
        $this->get('/admin/suppliers?q=017123&type=local&status=active')->assertInertia(fn (Assert $page) => $page
            ->where('suppliers.total', 1)->where('suppliers.data.0.products_count', 2));
        $this->get('/admin/suppliers/report?supplier_id='.$supplier->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Suppliers/Report')->where('suppliers.total', 1)
            ->where('suppliers.data.0.manual_products_count', 1)->where('suppliers.data.0.dropshipping_products_count', 1));
        $csv = $this->get('/admin/suppliers/report?format=csv&supplier_id='.$supplier->id)->assertOk()->streamedContent();
        $this->assertStringContainsString('ABC-1', $csv);
        $this->assertStringNotContainsString('Inactive wholesaler', $csv);
        for ($i = 0; $i < 25; $i++) $this->supplier(['name' => 'Extra '.$i]);
        $this->get('/admin/suppliers?page=2')->assertInertia(fn (Assert $page) => $page->where('suppliers.current_page', 2)->has('suppliers.data', 2));
    }
}

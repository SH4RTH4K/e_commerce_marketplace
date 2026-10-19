<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Dropshipping\CategoryMapper;
use App\Services\Dropshipping\ProductImportService;
use App\Services\SkuConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SkuConfigurationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $root;
    private Category $sub;
    private Category $leaf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        $this->root = Category::create(['name' => 'Men', 'slug' => 'men']);
        $this->sub = Category::create(['name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $this->root->id]);
        $this->leaf = Category::create(['name' => 'Casual', 'slug' => 'casual', 'parent_id' => $this->sub->id]);
    }

    private function config(array $changes = []): array
    {
        return array_replace([
            'enabled' => true, 'components' => ['category', 'subcategory', 'subsubcategory', 'sequence'],
            'static_prefix' => '', 'suffix' => '', 'separator' => '-', 'case' => 'upper',
            'sequence_start' => 1, 'sequence_padding' => 4,
            'category_codes' => [$this->root->id => 'MEN', $this->sub->id => 'SHIRT', $this->leaf->id => 'CASUAL'],
        ], $changes);
    }

    private function product(?string $sku = null, array $attributes = []): Product
    {
        return Product::create(array_replace(['name' => 'Shirt', 'category_id' => $this->leaf->id, 'sku' => $sku,
            'source_type' => 'manual', 'regular_price' => 500, 'stock_quantity' => 3, 'is_published' => true], $attributes));
    }

    public function test_setup_is_separate_from_dropshipping_with_independent_category_prefixes(): void
    {
        Setting::put('dropshipping_enabled', '0');
        $this->get('/admin/sku-configuration')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Admin/SkuConfiguration')->has('categories', 3)->where('config.enabled', false));
        $this->patch('/admin/sku-configuration', $this->config())->assertSessionHasNoErrors()->assertRedirect('/admin/sku-configuration');
        $this->assertSame('CASUAL', app(SkuConfigurationService::class)->config()['category_codes'][$this->leaf->id]);
        $this->assertDatabaseHas('sku_configuration_runs', ['actor_id' => $this->admin->id, 'action' => 'configuration_saved']);
        $this->actingAs(User::factory()->create(['role' => 'inventory', 'permissions' => ['products.view']]))->get('/admin/sku-configuration')->assertForbidden();
        $this->postJson('/admin/sku-configuration/preview', ['config' => $this->config(), 'scope' => 'all', 'source' => 'all'])->assertForbidden();
    }

    public function test_saving_future_format_does_not_modify_existing_skus_and_new_manual_products_receive_unique_codes(): void
    {
        $existing = $this->product('KEEP-1');
        $this->patch('/admin/sku-configuration', $this->config())->assertSessionHasNoErrors();
        foreach (['MEN-SHIRT-CASUAL-0001', 'MEN-SHIRT-CASUAL-0002'] as $expected) {
            $this->post('/admin/products', ['name' => 'New Shirt', 'category_id' => $this->leaf->id, 'regular_price' => 500, 'stock_quantity' => 1, 'source_type' => 'manual'])->assertSessionHasNoErrors()->assertRedirect();
            $new = Product::latest('id')->first();
            $this->assertSame($expected, $new->sku);
            $this->assertTrue($new->sku_managed);
        }
        $this->assertSame('KEEP-1', $existing->fresh()->sku);
        $this->post('/admin/products', ['name' => 'Override', 'category_id' => $this->leaf->id, 'regular_price' => 500, 'stock_quantity' => 1, 'sku' => 'CUSTOM-1'])->assertSessionHasNoErrors();
        $this->assertSame('CUSTOM-1', Product::latest('id')->first()->sku);
    }

    public function test_existing_preview_is_read_only_and_apply_respects_missing_scope_and_keeps_old_urls(): void
    {
        $keep = $this->product('KEEP'); $missing = $this->product();
        $body = ['config' => $this->config(), 'scope' => 'missing', 'source' => 'all'];
        $preview = $this->postJson('/admin/sku-configuration/preview', $body)->assertOk()->assertJsonPath('changed_count', 1)->json();
        $this->assertNull($missing->fresh()->sku);
        $this->assertFalse(app(SkuConfigurationService::class)->config()['enabled']);
        $this->post('/admin/sku-configuration/apply', ['token' => $preview['token'], 'confirm' => true])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('KEEP', $keep->fresh()->sku);
        $this->assertSame('MEN-SHIRT-CASUAL-0001', $missing->fresh()->sku);
        $this->assertDatabaseHas('sku_change_items', ['product_id' => $missing->id, 'new_sku' => 'MEN-SHIRT-CASUAL-0001']);
        $oldUrl = route('product.show', $missing->fresh());
        $newConfig = $this->config(['components' => ['static_prefix', 'sequence'], 'static_prefix' => 'TL']);
        $preview = $this->postJson('/admin/sku-configuration/preview', ['config' => $newConfig, 'scope' => 'all', 'source' => 'all'])->assertOk()->json();
        $this->post('/admin/sku-configuration/apply', ['token' => $preview['token'], 'confirm' => true])->assertSessionHasNoErrors();
        $newUrl = route('product.show', $missing->fresh());
        $this->get($oldUrl)->assertRedirect($newUrl)->assertStatus(301);
        $this->get($newUrl)->assertOk();
        $this->assertSame('TL-0001', $missing->fresh()->sku);
        // Reconfiguring prefixes preserves each product's sequence allocation.
        $this->assertSame(1, $missing->fresh()->sku_sequence);
    }

    public function test_stale_previews_and_cross_admin_tokens_cannot_apply(): void
    {
        $product = $this->product('OLD');
        $preview = $this->postJson('/admin/sku-configuration/preview', ['config' => $this->config(), 'scope' => 'all', 'source' => 'all'])->assertOk()->json();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/admin/sku-configuration/apply', ['token' => $preview['token'], 'confirm' => true])->assertSessionHasErrors('preview');
        $this->actingAs($this->admin);
        $product->update(['sku' => 'CHANGED']);
        $this->post('/admin/sku-configuration/apply', ['token' => $preview['token'], 'confirm' => true])->assertSessionHasErrors('preview');
        $this->assertSame('CHANGED', $product->fresh()->sku);
        $this->assertDatabaseCount('sku_change_items', 0);
    }

    public function test_invalid_and_duplicate_formats_cannot_be_applied(): void
    {
        $this->patch('/admin/sku-configuration', $this->config(['components' => ['category']]))->assertSessionHasErrors('components');
        $this->product('ONE'); $this->product('TWO');
        $bad = $this->config(['category_codes' => []]);
        $preview = $this->postJson('/admin/sku-configuration/preview', ['config' => $bad, 'scope' => 'all', 'source' => 'all'])->assertOk()->json();
        $this->assertCount(2, $preview['errors']);
        $this->post('/admin/sku-configuration/apply', ['token' => $preview['token'], 'confirm' => true])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('sku_change_items', 0);
    }

    public function test_import_generates_local_code_and_reimport_and_disabled_generation_preserve_it(): void
    {
        $this->patch('/admin/sku-configuration', $this->config())->assertSessionHasNoErrors();
        $supplier = DropshipSupplier::create(['name' => 'Mohasagor Bangladesh', 'key' => 'main', 'driver_key' => 'fake', 'is_active' => true]);
        $source = DropshipSupplierProduct::create(['supplier_id' => $supplier->id, 'supplier_product_id' => '9900', 'product_code' => '12900',
            'name' => 'Supplier Shirt', 'supplier_category_key' => 'shirts', 'currency' => 'BDT', 'cost_price' => 350, 'max_price' => 690, 'stock_qty' => 3, 'is_available' => true, 'raw_payload' => [], 'fetched_at' => now(), 'last_seen_at' => now()]);
        app(CategoryMapper::class)->mapManually($supplier, 'shirts', $this->leaf);
        $importer = app(ProductImportService::class);
        $product = $importer->import($source)->product;
        $this->assertSame('MEN-SHIRT-CASUAL-0001', $product->sku);
        $this->assertTrue($product->sku_managed);
        $this->patch('/admin/sku-configuration', $this->config(['enabled' => false]));
        $source->update(['product_code' => '12901']);
        $importer->import($source);
        $this->assertSame('MEN-SHIRT-CASUAL-0001', $product->fresh()->sku);
        $this->assertSame('9900', $source->fresh()->supplier_product_id);
        $this->assertSame('12901', $source->fresh()->product_code);
    }

    public function test_empty_separator_and_lowercase_work_and_counter_never_resets(): void
    {
        $config = $this->config(['components' => ['category', 'sequence'], 'separator' => '', 'case' => 'lower', 'sequence_start' => 50]);
        $this->patch('/admin/sku-configuration', $config)->assertSessionHasNoErrors();
        $first = $this->product();
        DB::transaction(fn () => app(SkuConfigurationService::class)->assignNew($first));
        $this->assertSame('men0050', $first->sku);
        $this->patch('/admin/sku-configuration', array_replace($config, ['sequence_start' => 1]))->assertSessionHasNoErrors();
        $second = $this->product();
        DB::transaction(fn () => app(SkuConfigurationService::class)->assignNew($second));
        $this->assertSame('men0051', $second->sku);
        $this->assertSame(52, app(SkuConfigurationService::class)->nextSequence($config));
    }
}

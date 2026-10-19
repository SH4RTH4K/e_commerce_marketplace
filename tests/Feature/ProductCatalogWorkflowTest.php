<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductCatalogWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'catalog'], ['name' => 'Catalog']);
        return Product::create(array_merge(['name' => 'Catalog Product', 'category_id' => $category->id, 'regular_price' => 500, 'stock_quantity' => 10, 'is_published' => true], $attributes));
    }

    public function test_search_normalizes_spaces_and_matches_all_words_across_name_sku_and_brand(): void
    {
        $product = $this->product(['name' => 'Portable Wireless Speaker', 'brand' => 'JBL', 'sku' => 'FLIP-7']);
        $this->product(['name' => 'Portable Watch', 'brand' => 'JBL']);
        $this->get('/admin/products?q='.urlencode('  JBL   speaker  '))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'JBL speaker')->has('products.data', 1)->where('products.data.0.id', $product->id));
        $this->get('/admin/products?q=FLIP-7')->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
    }

    public function test_supplier_code_is_exposed_and_searchable_in_the_product_catalog(): void
    {
        $supplier = DropshipSupplier::create(['key' => 'catalog-code-supplier', 'name' => 'Code Supplier', 'supplier_code' => 'CSUP', 'driver_key' => 'fake', 'base_url' => 'https://supplier.example']);
        $product = $this->product(['name' => 'Supplier Linked Product']);
        $source = DropshipSupplierProduct::create(['supplier_id' => $supplier->id, 'supplier_product_id' => '9900', 'product_code' => 'SUP-12900', 'name' => $product->name, 'currency' => 'BDT', 'cost_price' => 100, 'stock_qty' => 3, 'fetched_at' => now(), 'raw_payload' => []]);
        DropshipProductLink::create(['supplier_product_row_id' => $source->id, 'product_id' => $product->id, 'sync_status' => 'active', 'field_sync_rules' => []]);

        $this->get('/admin/products?q=SUP-12900')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $product->id)
            ->where('products.data.0.supplier_code', 'SUP-12900')
            ->where('products.data.0.supplier_product_id', '9900')
            ->where('products.data.0.supplier_account_code', 'CSUP'));
    }

    public function test_smart_search_ranks_exact_sku_before_partial_name_matches(): void
    {
        $exact = $this->product(['sku' => 'ABC-7']); $this->product(['name' => 'New ABC-7 Accessories']);
        $this->get('/admin/products?q=ABC-7')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'relevance')->has('products.data', 2)->where('products.data.0.id', $exact->id));
    }

    #[DataProvider('literalTerms')]
    public function test_sql_wildcards_and_quotes_are_literal_search_text(string $term): void
    {
        $matching = $this->product(['name' => 'Literal '.$term.' Product']); $this->product(['name' => 'Unrelated Product']);
        $this->get('/admin/products?q='.urlencode($term))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)->where('products.data.0.id', $matching->id));
    }

    public static function literalTerms(): array { return [['50%'], ['SKU_7'], ['WOW!'], ["O'Reilly"]]; }

    public function test_zero_search_term_is_retained(): void
    {
        $this->product(['sku' => '0']); $this->product(['name' => 'Different Product']);
        $this->get('/admin/products?q=0')->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.q', '0')->has('products.data', 1));
    }

    public function test_bookmarked_visibility_aliases_and_invalid_status_do_not_hide_published_products(): void
    {
        $this->product(); $this->product(['is_published' => false]);
        $this->get('/admin/products?status=active')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'published')->has('products.data', 1)->where('products.data.0.is_published', true));
        $this->get('/admin/products?status=inactive')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'draft')->has('products.data', 1)->where('products.data.0.is_published', false));
        $this->get('/admin/products?status=unknown')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', '')->has('products.data', 2));
    }

    public function test_category_stock_type_and_supplier_filters_combine_with_scoped_visibility_counts(): void
    {
        $matching = $this->product(['name' => 'Filtered Product', 'stock_quantity' => 3]);
        $matching->variants()->create(['type' => 'Color', 'value' => 'Blue', 'stock' => 3]);
        $supplier = DropshipSupplier::create(['key' => 'catalog-supplier', 'name' => 'Catalog Supplier', 'driver_key' => 'fake', 'base_url' => 'https://supplier.example']);
        $source = DropshipSupplierProduct::create(['supplier_id' => $supplier->id, 'supplier_product_id' => 'catalog-1', 'name' => 'Filtered Product', 'currency' => 'BDT', 'cost_price' => 100, 'stock_qty' => 3, 'fetched_at' => now(), 'raw_payload' => []]);
        DropshipProductLink::create(['supplier_product_row_id' => $source->id, 'product_id' => $matching->id, 'sync_status' => 'active', 'field_sync_rules' => []]);
        $this->product(['stock_quantity' => 0, 'is_published' => false]);
        $this->get('/admin/products?category='.$matching->category_id.'&supplier='.$supplier->id.'&stock=low&type=variable&status=published')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $matching->id)
                ->where('summary.all', 1)->where('summary.published', 1)->where('summary.draft', 0));
    }

    public function test_customer_price_includes_zero_discount_and_images_use_resolved_storage_urls(): void
    {
        $zero = $this->product(['sale_price' => 0]); $this->product(['regular_price' => 200]);
        $zero->images()->create(['path' => 'products/catalog.jpg', 'position' => 0]);
        $this->get('/admin/products?sort=price_asc')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.id', $zero->id)->where('products.data.0.price', '0.00')
            ->where('products.data.0.image_url', asset('storage/products/catalog.jpg')));
    }

    public function test_pagination_carries_normalized_parameters_and_omits_unknown_inputs(): void
    {
        for ($i = 0; $i < 17; $i++) $this->product(['sku' => 'PAGE-'.$i]);
        $this->get('/admin/products?q=PAGE&status=active&per_page=15&unused=ignored')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 15)->where('products.next_page_url', fn ($url) => str_contains($url, 'q=PAGE') && str_contains($url, 'status=published') && !str_contains($url, 'unused')));
        $this->get('/admin/products?q=PAGE&status=published&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 2));
        $this->get('/admin/products?per_page=30')->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 17)->where('filters.per_page', 30));
    }

    public function test_malformed_parameters_are_rejected_and_unsupported_sort_sizes_normalized(): void
    {
        $this->getJson('/admin/products?q%5B%5D=oops')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/admin/products?category=not-an-id')->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->get('/admin/products?sort=bad&per_page=1000')->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'newest')->where('filters.per_page', 15));
    }

    public function test_view_only_staff_cannot_edit_publish_delete_or_bulk_change_products(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create(['role' => 'inventory', 'permissions' => ['products.view']]))->get('/admin/products')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canEdit', false)->where('canDelete', false));
        $this->get('/admin/products/'.$product->id.'/edit')->assertForbidden();
        $this->patch('/admin/products/'.$product->id.'/toggle', ['is_published' => false])->assertForbidden();
        $this->delete('/admin/products/'.$product->id)->assertForbidden();
        $this->post('/admin/products/bulk', ['ids' => [$product->id], 'bulk_action' => 'deactivate'])->assertForbidden();
        $this->assertTrue($product->fresh()->is_published);
    }

    public function test_explicit_visibility_changes_are_idempotent_and_bulk_updates_invalidate_sitemap(): void
    {
        $product = $this->product();
        for ($i = 0; $i < 2; $i++) $this->patch('/admin/products/'.$product->id.'/toggle', ['is_published' => false])->assertSessionHasNoErrors();
        $this->assertFalse($product->fresh()->is_published);
        Cache::put('sitemap_urls_v2', ['stale']);
        $this->post('/admin/products/bulk', ['ids' => [$product->id], 'bulk_action' => 'activate'])->assertSessionHasNoErrors();
        $this->assertTrue($product->fresh()->is_published); $this->assertNull(Cache::get('sitemap_urls_v2'));
        $this->postJson('/admin/products/bulk', ['ids' => [$product->id, $product->id], 'bulk_action' => 'delete'])->assertUnprocessable();
        $this->assertNotNull($product->fresh());
    }

    public function test_creating_a_product_redirects_to_numeric_admin_editor_and_retains_list_context(): void
    {
        $category = Category::create(['name' => 'New Products', 'slug' => 'new-products']);
        $return = '/admin/products?q=New&status=draft';
        $response = $this->post('/admin/products', ['category_id' => $category->id, 'name' => 'New Product', 'regular_price' => 100, 'stock_quantity' => 1, 'return_to' => $return])->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $response->assertRedirect(route('admin.products.edit', ['product' => $product->id, 'return_to' => $return]));
        $this->get($response->headers->get('Location'))->assertOk();
        $this->get('/admin/products/'.$product->id.'/edit?return_to='.urlencode($return))->assertOk()->assertInertia(fn (Assert $page) => $page->where('returnTo', $return));
        $this->put('/admin/products/'.$product->id, ['name' => $product->name, 'category_id' => $category->id, 'regular_price' => 100, 'stock_quantity' => 1, 'return_to' => $return])
            ->assertRedirect(route('admin.products.edit', ['product' => $product->id, 'return_to' => $return]));
    }
}

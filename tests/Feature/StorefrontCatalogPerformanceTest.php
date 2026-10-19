<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontCatalogPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Setting::put('seo_keyword_consistency_enabled', '0');
        Setting::put('storefront_template', 'template-2');
        Setting::put('template_2_products_per_page', '12');
    }

    public function test_catalogue_cards_keep_customer_fields_without_sending_product_page_content_or_supplier_payloads(): void
    {
        $category = $this->category();
        $product = $this->product($category, [
            'description' => str_repeat('Full product description. ', 1000),
            'short_description' => 'Customer details on the product page',
            'specifications' => [['label' => 'Material', 'value' => 'Cotton']],
            'sku' => '11434',
            'sale_price' => 0,
            'stock_quantity' => 0,
        ]);
        $image = $product->images()->create(['path' => 'products/shirt.jpg', 'is_primary' => true, 'position' => 0]);
        $supplier = DropshipSupplier::create(['key' => 'catalogue', 'name' => 'Catalogue supplier', 'driver_key' => 'manual']);
        $source = DropshipSupplierProduct::create([
            'supplier_id' => $supplier->id,
            'supplier_product_id' => 'source-shirt',
            'product_code' => '11434',
            'name' => 'Supplier shirt',
            'currency' => 'BDT',
            'cost_price' => 100,
            'stock_qty' => 0,
            'fetched_at' => now(),
            'raw_payload' => ['large' => str_repeat('Supplier data', 1000)],
        ]);
        DropshipProductLink::create(['supplier_product_row_id' => $source->id, 'product_id' => $product->id, 'sync_status' => 'active']);

        $this->get('/category/shirt')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.id', $product->id)
            ->where('products.data.0.sku', '11434')
            ->where('products.data.0.url_key', $product->slug.'-11434')
            ->where('products.data.0.sale_price', '0.00')
            ->where('products.data.0.stock_quantity', 0)
            ->where('products.data.0.variants_exists', false)
            ->where('products.data.0.images.0.id', $image->id)
            ->where('products.data.0.images.0.path', 'products/shirt.jpg')
            ->where('products.data.0.category.name', 'Shirt')
            ->missing('products.data.0.description')
            ->missing('products.data.0.short_description')
            ->missing('products.data.0.specifications')
            ->missing('products.data.0.supplier_links'));

        $this->assertSame($product->description, $product->fresh()->description);
        $this->get(route('product.show', $product))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('product.description', $product->description));
    }

    public function test_serializing_many_cards_reuses_variant_existence_instead_of_querying_each_product(): void
    {
        $category = $this->category();
        for ($i = 0; $i < 12; $i++) {
            $product = $this->product($category, ['name' => 'Shirt '.$i]);
            if ($i % 2 === 0) {
                $product->variants()->create(['type' => 'Size', 'value' => 'M', 'stock' => 2, 'price_delta' => 0]);
            }
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get('/category/shirt')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertCount(0, $queries->filter(fn ($sql) => preg_match('/^select exists\(select \* from ["`]product_variants["`]/i', $sql)), 'withExists must not be followed by a variant query for each serialized card.');
        $response->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 12)
            ->where('products.data.0.variants_exists', false)
            ->where('products.data.1.variants_exists', true));
    }

    public function test_load_more_requests_do_not_rebuild_sidebar_aggregates_or_filters(): void
    {
        $category = $this->category();
        for ($i = 0; $i < 13; $i++) {
            $this->product($category, ['name' => 'Shirt '.$i]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
            'X-Inertia-Partial-Component' => 'Storefront/Shop',
            'X-Inertia-Partial-Data' => 'products',
        ])->get('/category/shirt?page=2')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $response->assertJsonCount(1, 'props.products.data')
            ->assertJsonPath('props.products.total', 13)
            ->assertJsonMissingPath('props.brands')
            ->assertJsonMissingPath('props.variantFilters')
            ->assertJsonMissingPath('props.filterCategories')
            ->assertJsonMissingPath('props.priceCeiling');
        $this->assertStringNotContainsString('MAX(COALESCE(', $queries);
        $this->assertStringNotContainsString('COUNT(DISTINCT product_id)', $queries);
        $this->assertDoesNotMatchRegularExpression('/select distinct ["`]brand["`]/i', $queries);
        $this->assertDoesNotMatchRegularExpression('/group by ["`]category_id["`]/i', $queries);
    }

    public function test_sidebar_data_and_zero_prices_remain_current_after_catalogue_changes(): void
    {
        $category = $this->category();
        $product = $this->product($category, ['brand' => 'First brand', 'regular_price' => 5051]);
        $product->variants()->create(['type' => 'Size', 'value' => 'M', 'stock' => 2, 'price_delta' => 0]);
        $this->get('/category/shirt')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('allProductsCount', 1)->where('priceCeiling', 5100)
            ->where('brands', ['First brand'])->where('variantFilters.0.options.0.value', 'M'));

        $product->update(['brand' => 'Updated brand', 'sale_price' => 0]);
        $product->variants()->update(['value' => 'L']);
        $this->get('/category/shirt')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('allProductsCount', 1)->where('priceCeiling', 5000)
            ->where('brands', ['Updated brand'])->where('variantFilters.0.options.0.value', 'L')
            ->where('products.total', 1)->where('products.data.0.sale_price', '0.00'));
    }

    private function category(): Category
    {
        return Category::create(['name' => 'Shirt', 'slug' => 'shirt', 'is_active' => true, 'show_in_menu' => true]);
    }

    private function product(Category $category, array $attributes = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Shirt',
            'regular_price' => 500,
            'stock_quantity' => 5,
            'is_published' => true,
        ], $attributes));
    }
}

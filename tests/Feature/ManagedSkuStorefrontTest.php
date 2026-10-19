<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ManagedSkuStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Setting::put('seo_keyword_consistency_enabled', '0');
    }

    public function test_local_sku_is_used_on_product_category_homepage_and_merchant_feed(): void
    {
        $product = $this->linkedProduct('TL000123');

        $this->get(route('product.show', $product))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('product.sku', 'TL000123')
                ->where('product.url_key', 'royal-jewelry-TL000123'));

        $this->get('/category/jewelry')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.data.0.sku', 'TL000123')
                ->where('products.data.0.url_key', 'royal-jewelry-TL000123'));

        $this->get('/')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('templateTwoCategorySections.0.products.0.sku', 'TL000123'));

        $feed = $this->get('/merchant-feed.xml')->assertOk()->streamedContent();
        $this->assertStringContainsString('/product/royal-jewelry-TL000123', $feed);
        $this->assertStringNotContainsString('/product/royal-jewelry-12900', $feed);
        $this->assertSame('TL000123', $product->fresh()->sku);
        $this->assertDatabaseHas('dropship_supplier_products', ['supplier_product_id' => '9900', 'product_code' => '12900']);
    }

    public function test_legacy_products_without_local_sku_can_still_display_the_supplier_code(): void
    {
        $product = $this->linkedProduct(null);

        $this->get('/category/jewelry')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.data.0.sku', '12900')
                ->where('products.data.0.url_key', 'royal-jewelry-12900'));

        $this->assertNull($product->fresh()->sku);
    }

    private function linkedProduct(?string $sku): Product
    {
        $category = Category::create(['name' => 'Jewelry', 'slug' => 'jewelry', 'is_active' => true, 'show_in_menu' => true]);
        $supplier = DropshipSupplier::create(['key' => 'mohasagor', 'name' => 'Mohasagor Bangladesh', 'is_active' => true]);
        $source = DropshipSupplierProduct::create([
            'supplier_id' => $supplier->id, 'supplier_product_id' => '9900', 'product_code' => '12900',
            'name' => 'Royal Jewelry', 'currency' => 'BDT', 'stock_qty' => 3, 'is_available' => true,
            'raw_payload' => [], 'fetched_at' => now(), 'last_seen_at' => now(),
        ]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Royal Jewelry', 'slug' => 'royal-jewelry',
            'sku' => $sku, 'regular_price' => 690, 'stock_quantity' => 3, 'is_published' => true,
        ]);
        $product->images()->create(['path' => 'https://example.com/jewelry.jpg', 'is_primary' => true, 'position' => 0]);
        DropshipProductLink::create(['supplier_product_row_id' => $source->id, 'product_id' => $product->id, 'sync_status' => 'active']);

        return $product;
    }
}

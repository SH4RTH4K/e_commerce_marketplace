<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_enable_and_disable_ordering_without_changing_real_stock(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();
        $data = ['name' => $product->name, 'category_id' => $product->category_id, 'is_published' => true];

        $this->put('/admin/products/'.$product->id, [...$data, 'allow_backorder' => true])->assertRedirect()->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertTrue($product->allow_backorder);
        $this->assertSame(0, $product->stock_quantity);
        $this->assertSame(0, $product->sourced_stock_quantity);
        $this->get('/admin/products/'.$product->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('product.allow_backorder', true)->where('product.stock_quantity', 0));

        $this->put('/admin/products/'.$product->id, [...$data, 'allow_backorder' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($product->fresh()->allow_backorder);
        $this->assertFalse($product->fresh()->can_order);
    }

    public function test_new_product_can_be_available_to_order_with_zero_opening_stock(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $category = $this->category();

        $this->post('/admin/products', ['name' => 'Source on order clock', 'category_id' => $category->id,
            'regular_price' => 300, 'stock_quantity' => 0, 'allow_backorder' => true, 'is_published' => true])
            ->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertTrue($product->allow_backorder);
        $this->assertSame(0, $product->stock_quantity);
        $this->assertSame('Available to order', $product->availability_label);
    }

    public function test_switch_rejects_invalid_values_and_general_product_edit_cannot_forge_sourcing_receipts(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();
        $data = ['name' => $product->name, 'category_id' => $product->category_id];

        $this->putJson('/admin/products/'.$product->id, [...$data, 'allow_backorder' => 'always'])
            ->assertUnprocessable()->assertJsonValidationErrors('allow_backorder');
        $this->putJson('/admin/products/'.$product->id, [...$data, 'allow_backorder' => true, 'sourced_stock_quantity' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors('sourced_stock_quantity');

        $this->assertFalse($product->fresh()->allow_backorder);
        $this->assertSame(0, $product->fresh()->sourced_stock_quantity);
    }

    public function test_read_only_catalog_staff_cannot_change_ordering_override(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager', 'permissions' => ['products.view']]));
        $product = $this->product();

        $this->put('/admin/products/'.$product->id, ['name' => $product->name, 'category_id' => $product->category_id, 'allow_backorder' => true])
            ->assertForbidden();
        $this->assertFalse($product->fresh()->allow_backorder);
    }

    public function test_product_page_schema_and_fallback_expose_backorder_without_faking_stock(): void
    {
        Setting::put('seo_fallback_h1_enabled', '1');
        $product = $this->product(['allow_backorder' => true]);

        $this->get(route('product.show', $product))->assertOk()
            ->assertSee('<p>Available to order</p>', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.stock_quantity', 0)->where('product.can_order', true)
                ->where('product.hide_stock_quantity', true)->where('product.available_stock_quantity', 0)
                ->where('structuredData.@graph.0.offers.availability', 'https://schema.org/BackOrder'));

        $product->update(['allow_backorder' => false]);
        $this->get(route('product.show', $product))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('product.can_order', false)->where('product.hide_stock_quantity', false)
            ->where('structuredData.@graph.0.offers.availability', 'https://schema.org/OutOfStock'));
    }

    public function test_received_local_units_are_orderable_after_disabling_the_switch_and_reported_in_stock(): void
    {
        $product = $this->product(['sourced_stock_quantity' => 2]);

        $this->get('/shop?in_stock=1')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)->where('products.data.0.id', $product->id)
            ->where('products.data.0.available_stock_quantity', 2)->where('products.data.0.can_order', true));
        $this->get(route('product.show', $product))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('product.availability_label', 'In Stock')
            ->where('structuredData.@graph.0.offers.availability', 'https://schema.org/InStock'));
        $this->get('/shop?out_of_stock=1')->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    }

    public function test_storefront_stock_filters_and_banners_allow_sourcing_products_but_backend_reports_zero_stock(): void
    {
        Setting::put('storefront_template', 'template-1');
        $product = $this->product(['allow_backorder' => true, 'is_featured' => true]);
        $unavailable = $this->product(['name' => 'Unavailable clock']);
        Banner::create(['title' => 'Source on order', 'placement' => 'hero', 'product_id' => $product->id, 'is_active' => true]);

        $this->get('/shop?in_stock=1')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)->where('products.data.0.id', $product->id)->where('products.data.0.allow_backorder', true));
        $this->get('/shop?out_of_stock=1')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)->where('products.data.0.id', $unavailable->id));
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('heroBanners', 1)->where('trending.0.id', $product->id));

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/admin/products?stock=out')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)->where('products.data.1.stock_quantity', 0)->where('products.data.1.allow_backorder', true));
        $this->get('/admin/inventory?stock_level=out')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)->where('summary.out_stock', 2)->where('summary.units', 0));
    }

    public function test_inventory_separates_supplier_availability_from_received_local_balance_and_override(): void
    {
        $product = $this->product(['source_type' => 'dropshipping', 'stock_quantity' => 0, 'sourced_stock_quantity' => 2, 'allow_backorder' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin/inventory?stock_level=low')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)->where('products.data.0.id', $product->id)
            ->where('products.data.0.stock_quantity', 0)->where('products.data.0.supplier_stock_quantity', 0)
            ->where('products.data.0.local_stock_quantity', 2)->where('products.data.0.available_stock_quantity', 2)
            ->where('products.data.0.allow_backorder', true)->where('summary.units', 2)->where('summary.supplier_units', 0));
        $this->get('/admin/products?stock=out')->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    }

    public function test_merchant_feed_excludes_undated_backorders_until_actual_stock_is_available(): void
    {
        $product = $this->product(['allow_backorder' => true]);
        $product->images()->create(['path' => 'products/clock.jpg', 'is_primary' => true]);

        $xml = simplexml_load_string($this->get('/merchant-feed.xml')->assertOk()->streamedContent());
        $this->assertCount(0, $xml->channel->item);

        $product->update(['sourced_stock_quantity' => 1]);
        $xml = simplexml_load_string($this->get('/merchant-feed.xml')->assertOk()->streamedContent());
        $this->assertCount(1, $xml->channel->item);
        $fields = $xml->channel->item[0]->children('http://base.google.com/ns/1.0');
        $this->assertSame('in_stock', (string) $fields->availability);
    }

    public function test_guides_include_orderable_products_with_explicit_availability_labels(): void
    {
        $product = $this->product(['allow_backorder' => true]);
        $this->product(['name' => 'Unavailable speaker']);

        $this->get('/blog/bluetooth-speaker-buying-guide-bangladesh')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('guideProducts', 1)->where('guideProducts.0.url', route('product.show', $product))
            ->where('guideProducts.0.availability_label', 'Available to order'));
    }

    private function category(): Category
    {
        return Category::firstOrCreate(['slug' => 'bluetooth-speaker'], ['name' => 'Bluetooth Speakers', 'is_active' => true, 'show_in_menu' => true]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(['category_id' => $this->category()->id, 'name' => 'Clock',
            'regular_price' => 300, 'stock_quantity' => 0, 'is_published' => true, ...$attributes]);
    }
}

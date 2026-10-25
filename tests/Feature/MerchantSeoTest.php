<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\StorefrontSeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MerchantSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Setting::put('site_name', 'TAQI LIFE');
    }

    private function product(array $data = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'clothing'], ['name' => 'Clothing', 'is_active' => true]);

        return Product::create(array_merge(['name' => 'Shirt & jacket', 'category_id' => $category->id, 'regular_price' => 1000, 'sale_price' => 800, 'stock_quantity' => 3, 'sku' => 'S1', 'is_published' => true], $data));
    }

    public function test_variant_prices_and_preselected_links_match_cart_pricing(): void
    {
        $product = $this->product();
        $small = $product->variants()->create(['type' => 'Size', 'value' => 'S', 'price_delta' => 0]);
        $large = $product->variants()->create(['type' => 'Size', 'value' => 'L', 'price_delta' => 100]);
        $black = $product->variants()->create(['type' => 'Color', 'value' => 'Black', 'price_delta' => -50]);
        $white = $product->variants()->create(['type' => 'Color', 'value' => 'White', 'price_delta' => 25]);
        $schema = app(StorefrontSeoService::class)->product($product->load('images', 'variants'), []);
        $group = $schema['@graph'][0];
        $this->assertSame('ProductGroup', $group['@type']);
        $this->assertSame(['750.00', '825.00', '850.00', '925.00'], array_column(array_column($group['hasVariant'], 'offers'), 'price'));
        $this->assertCount(4, array_unique(array_column($group['hasVariant'], 'sku')));
        $url = route('product.show', $product).'?variant='.$large->id.'.'.$white->id;
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('initialVariants.Size', 'L')->where('initialVariants.Color', 'White')
            ->where('seo.canonical', route('product.show', $product)));
        $this->post('/cart/add', ['product_id' => $product->id, 'variant' => 'Size: L, Color: White', 'qty' => 1])->assertRedirect();
        $this->assertSame(925.0, app(CartService::class)->subtotal());
        foreach (['999999', $small->id.'.'.$large->id, $small->id.'.'.$black->id.'.'.$white->id] as $invalid) {
            $this->get(route('product.show', $product).'?variant='.$invalid)->assertNotFound();
        }
    }

    public function test_feed_is_valid_xml_contains_only_public_products_and_current_prices(): void
    {
        $product = $this->product(['description' => '<p>100% cotton &amp; two pockets.</p>', 'is_free_shipping' => true]);
        $product->images()->create(['path' => 'https://images.example.com/shirt.png', 'is_primary' => true]);
        $product->variants()->create(['type' => 'Size', 'value' => 'S', 'price_delta' => 0]);
        $product->variants()->create(['type' => 'Size', 'value' => 'L', 'price_delta' => 100]);
        $draft = $this->product(['name' => 'Private draft', 'is_published' => false]);
        $draft->images()->create(['path' => 'https://images.example.com/draft.png']);
        $response = $this->get('/merchant-feed.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->streamedContent());
        $this->assertNotFalse($xml);
        $items = $xml->channel->item;
        $this->assertCount(2, $items);
        $first = $items[0]->children('http://base.google.com/ns/1.0');
        $this->assertSame('800.00 BDT', (string) $first->price);
        $this->assertSame('100% cotton & two pockets.', (string) $first->description);
        $this->assertSame('0.00 BDT', (string) $first->shipping->price);
        $this->assertSame('Shirt & jacket', (string) $first->item_group_title);
        $this->assertSame('Size', (string) $first->variant_option->name);
        $this->assertSame('S', (string) $first->variant_option->value);
        $this->assertFalse(isset($first->gtin));
        $this->assertFalse(isset($first->identifier_exists));
        $product->update(['sale_price' => 0, 'stock_quantity' => 0]);
        $fresh = simplexml_load_string($this->get('/merchant-feed.xml')->streamedContent())->channel->item[0]->children('http://base.google.com/ns/1.0');
        $this->assertSame('0.00 BDT', (string) $fresh->price);
        $this->assertSame('out_of_stock', (string) $fresh->availability);
        $product->update(['meta_title' => 'Cotton shirt | TAQI LIFE', 'meta_description' => 'Cotton shirt with two pockets.']);
        $english = simplexml_load_string($this->get('/merchant-feed.xml')->streamedContent())->channel->item[0]->children('http://base.google.com/ns/1.0');
        $this->assertSame('Cotton shirt — Size: S', (string) $english->title);
        $this->assertSame('Cotton shirt with two pockets.', (string) $english->description);
    }

    public function test_only_approved_reviews_are_used_and_return_policy_does_not_promise_free_returns(): void
    {
        $product = $this->product();
        $product->reviews()->create(['author_name' => 'Customer A', 'rating' => 4, 'body' => 'Useful product', 'status' => 'approved']);
        $product->reviews()->create(['author_name' => 'Customer B', 'rating' => 1, 'body' => 'Pending review', 'status' => 'pending']);
        DB::enableQueryLog();
        $schema = app(StorefrontSeoService::class)->product($product->load('images', 'variants'), []);
        $reviewQueries = array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'COUNT(*) AS review_count'));
        DB::disableQueryLog();
        $this->assertCount(1, $reviewQueries);
        $this->assertStringNotContainsString('order by', strtolower(array_values($reviewQueries)[0]['query']), 'Review aggregation must work with MySQL ONLY_FULL_GROUP_BY.');
        $this->assertSame(1, $schema['@graph'][0]['aggregateRating']['reviewCount']);
        $this->assertSame(4.0, $schema['@graph'][0]['aggregateRating']['ratingValue']);
        $this->assertCount(1, $schema['@graph'][0]['review']);
        $this->assertSame('Review', $schema['@graph'][0]['review'][0]['@type']);
        $this->assertSame('Customer A', $schema['@graph'][0]['review'][0]['author']['name']);
        $this->assertSame(4, $schema['@graph'][0]['review'][0]['reviewRating']['ratingValue']);
        $this->assertSame('Useful product', $schema['@graph'][0]['review'][0]['reviewBody']);
        $this->assertNotSame('Pending review', $schema['@graph'][0]['review'][0]['reviewBody']);
        $policy = app(StorefrontSeoService::class)->organization()['@graph'][0]['hasMerchantReturnPolicy'];
        $this->assertSame(route('refund'), $policy['merchantReturnLink']);
        $this->assertArrayNotHasKey('returnFees', $policy);
        $this->assertArrayNotHasKey('shippingDetails', $schema['@graph'][0]['offers']);
    }

    public function test_guides_are_discoverable_and_unknown_article_is_not_a_duplicate(): void
    {
        $this->get('/blog')->assertOk()->assertInertia(fn (Assert $page) => $page->has('guides', 5));
        $this->get('/blog/clothing-size-guide')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('structuredData.@type', 'BlogPosting')->has('guide.sections', 4));
        $this->get('/blog/unknown')->assertNotFound();
        $this->get('/blog/winter-dresses')->assertStatus(301)->assertRedirect(route('blog'));
        $this->get('/sitemaps/blogs.xml')->assertSee(route('blog.detail', 'clothing-size-guide'), false);
    }

    public function test_direct_ga4_is_configured_with_gtm_and_admin_does_not_load_marketing_tags(): void
    {
        Setting::put('tracking_ga4_id', 'G-EXAMPLE123');
        Setting::put('tracking_gtm_id', 'GTM-EXAMPLE');
        $this->get('/')->assertSee('send_page_view: false', false)->assertSee('ga4Mode:', false);
        $this->get('/admin/login')->assertDontSee('googletagmanager.com/gtag/js', false)->assertDontSee("j.src='https://www.googletagmanager.com/gtm.js", false);
        Setting::put('tracking_ga4_mode', 'gtm');
        $this->get('/')->assertDontSee('googletagmanager.com/gtag/js', false);
    }
}

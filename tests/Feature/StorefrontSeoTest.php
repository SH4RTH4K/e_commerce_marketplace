<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Setting::put('seo_keyword_consistency_enabled', '0');
        Setting::put('site_name', 'TAQI LIFE');
    }

    public function test_paginated_catalogue_is_indexable_self_canonical_and_has_crawlable_links(): void
    {
        Setting::put('template_2_products_per_page', '12');
        $category = $this->category();
        for ($i = 0; $i < 35; $i++) {
            $this->product(['name' => 'Speaker '.$i]);
        }
        $response = $this->get('/category/speakers?page=2')->assertOk();
        $html = $this->html($response->getContent());
        $this->assertSame(route('shop.category', $category).'?page=2', $html->evaluate('string(//link[@rel="canonical"]/@href)'));
        $this->assertSame('index,follow', $html->evaluate('string(//meta[@name="robots"]/@content)'));
        $this->assertGreaterThan(0, $html->query('//*[@data-seo-crawler-fallback]//a[contains(@href,"page=3")]')->length);
        $this->assertGreaterThan(0, $html->query('//*[@data-seo-crawler-fallback]//a[contains(@href,"/product/")]')->length);
        $this->assertSame(1, $html->query('//*[@data-seo-crawler-fallback]//h1[@data-seo-fallback-h1]')->length);
        $this->get('/category/speakers?page=200')->assertNotFound();
        $filtered = $this->html($this->get('/category/speakers?brand=Example')->assertOk()->getContent());
        $this->assertSame('noindex,follow', $filtered->evaluate('string(//meta[@name="robots"]/@content)'));
        $tracked = $this->html($this->get('/category/speakers?utm_source=facebook')->assertOk()->getContent());
        $this->assertSame('index,follow', $tracked->evaluate('string(//meta[@name="robots"]/@content)'));
    }

    public function test_product_schema_matches_live_price_stock_and_nested_breadcrumbs_without_fake_ratings(): void
    {
        $root = $this->category();
        $child = Category::create(['name' => 'Portable', 'slug' => 'portable', 'is_active' => true, 'parent_id' => $root->id]);
        $product = $this->product(['category_id' => $child->id, 'sku' => 'ABC123', 'sale_price' => 0, 'stock_quantity' => 0]);
        $product->variants()->create(['type' => 'Color', 'value' => 'Black', 'stock' => 2, 'price_delta' => 0]);
        $schema = $this->schema($this->get(route('product.show', $product))->assertOk()->getContent());
        $item = $schema['@graph'][0];
        $this->assertSame('Product', $item['@type']);
        $this->assertSame('0.00', $item['offers']['price']);
        $this->assertSame('BDT', $item['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/OutOfStock', $item['offers']['availability']);
        $this->assertSame(route('product.show', $product), $item['url']);
        $this->assertArrayNotHasKey('aggregateRating', $item);
        $this->assertArrayNotHasKey('review', $item);
        $this->assertSame(['Home', 'Shop', 'Speakers', 'Portable', 'Speaker'], array_column($schema['@graph'][1]['itemListElement'], 'name'));
        $product->variants()->update(['stock' => 0]);
        $item = $this->schema($this->get(route('product.show', $product))->getContent())['@graph'][0];
        $this->assertSame('https://schema.org/OutOfStock', $item['offers']['availability']);
        $product->variants()->update(['price_delta' => 100]);
        $item = $this->schema($this->get(route('product.show', $product))->getContent())['@graph'][0];
        $this->assertSame('100.00', $item['offers']['price']);
    }

    public function test_structured_data_cannot_break_out_of_script_tags_and_saved_metadata_is_preserved(): void
    {
        $product = $this->product(['name' => 'Speaker </script><script>alert(1)</script>', 'meta_title' => 'Existing SEO title', 'meta_description' => 'Existing description']);
        $response = $this->get(route('product.show', $product))->assertOk();
        $schema = $this->schema($response->getContent());
        $this->assertSame($product->name, $schema['@graph'][0]['name']);
        $this->assertStringNotContainsString('</script><script>alert(1)</script>', $response->getContent());
        $this->assertSame('Existing SEO title', $this->html($response->getContent())->evaluate('string(//title)'));
        $this->assertSame('Existing description', $product->fresh()->meta_description);
    }

    public function test_business_schema_social_links_and_information_page_metadata_are_present_in_initial_html(): void
    {
        Setting::put('facebook_url', 'https://facebook.com/taqilife');
        $schema = $this->schema($this->get('/')->assertOk()->getContent());
        $this->assertSame('OnlineStore', $schema['@graph'][0]['@type']);
        $this->assertSame(['https://facebook.com/taqilife'], $schema['@graph'][0]['sameAs']);
        $this->assertSame('WebSite', $schema['@graph'][1]['@type']);
        $about = $this->html($this->get('/about')->assertOk()->getContent());
        $contact = $this->html($this->get('/contact')->assertOk()->getContent());
        $this->assertSame('About Us | TAQI LIFE', $about->evaluate('string(//title)'));
        $this->assertNotSame($about->evaluate('string(//meta[@name="description"]/@content)'), $contact->evaluate('string(//meta[@name="description"]/@content)'));
        $this->assertNotEmpty($about->evaluate('string(//meta[@property="og:image"]/@content)'));
        $alias = $this->html($this->get('/page/about')->assertOk()->getContent());
        $this->assertSame(route('about'), $alias->evaluate('string(//link[@rel="canonical"]/@href)'));
    }

    public function test_private_pages_emit_noindex_headers_and_meta_tags(): void
    {
        foreach (['/login', '/admin/login', '/wishlist', '/track'] as $uri) {
            $response = $this->get($uri)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertSame('noindex, nofollow', $this->html($response->getContent())->evaluate('string(//meta[@name="robots"]/@content)'));
        }
        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_sitemap_excludes_inactive_ancestor_categories_and_llms_uses_sku_canonical_urls(): void
    {
        $parent = $this->category();
        $parent->update(['is_active' => false]);
        $child = Category::create(['name' => 'Hidden branch', 'slug' => 'hidden-branch', 'is_active' => true, 'show_in_menu' => true, 'parent_id' => $parent->id]);
        $product = $this->product(['sku' => 'ABC123', 'sale_price' => 0]);
        $this->get('/category/hidden-branch')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()
            ->assertDontSee(route('shop.category', $child), false)
            ->assertSee('<loc>'.route('contact').'</loc>', false)
            ->assertSee('<loc>'.route('product.show', $product).'</loc>', false);
        $this->get('/llms.txt')->assertOk()->assertSee(route('product.show', $product), false)
            ->assertDontSee(route('shop.category', $child), false)->assertSee(money(0), false);
    }

    private function category(): Category
    {
        return Category::firstOrCreate(['slug' => 'speakers'], ['name' => 'Speakers', 'is_active' => true, 'show_in_menu' => true]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge(['category_id' => $this->category()->id, 'name' => 'Speaker', 'regular_price' => 1000, 'stock_quantity' => 5, 'is_published' => true], $attributes));
    }

    private function html(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return new DOMXPath($document);
    }

    private function schema(string $html): array
    {
        return json_decode($this->html($html)->evaluate('string(//script[@type="application/ld+json"])'), true, 512, JSON_THROW_ON_ERROR);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Support\ProductSlug;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProductUrlTest extends TestCase
{
    use RefreshDatabase;

    private const LONG_SLUG = 'jbl-flip-7-portable-waterproof-bluetooth-speaker-35w-ai-sound-boost-bluetooth-54-ip68-dustproof-16h-playtime-auracast-support-ds-2381-sjdhih';

    private const SHORT_SLUG = 'jbl-flip-7-portable-waterproof-bluetooth-speaker';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_new_products_automatically_get_short_readable_urls(): void
    {
        $title = 'JBL Flip 7 Portable Waterproof Bluetooth Speaker 35W AI Sound Boost';
        $product = $this->product(['name' => $title, 'slug' => self::LONG_SLUG]);
        $fromName = $this->product(['name' => 'Sony Portable Wireless Bluetooth Speaker With Bass Boost']);

        $this->assertSame(self::SHORT_SLUG, $product->slug);
        $this->assertSame($title, $product->name);
        $this->assertSame('sony-portable-wireless-bluetooth-speaker-with-bass', $fromName->slug);
        $this->get(route('product.show', $product))->assertOk();
    }

    public function test_compacting_normalizes_text_removes_importer_ids_and_bounds_length(): void
    {
        $this->assertSame('jbl-flip-7', ProductSlug::compact('JBL Flip 7-ds-2381-sjdhih'));
        $this->assertSame('sony-wireless-speaker', ProductSlug::compact('  Sony / Wireless Speaker!  '));
        $this->assertSame('product', ProductSlug::compact('...'));

        $slug = ProductSlug::compact(str_repeat('a', 90));

        $this->assertNotSame('', $slug);
        $this->assertLessThanOrEqual(55, strlen($slug));
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    public function test_colliding_titles_get_unique_urls_without_reusing_historical_urls(): void
    {
        $first = $this->product(['slug' => self::LONG_SLUG]);
        $second = $this->product(['slug' => self::LONG_SLUG]);

        $this->assertSame(self::SHORT_SLUG, $first->slug);
        $this->assertNotSame($first->slug, $second->slug);
        $this->assertStringStartsWith(self::SHORT_SLUG.'-', $second->slug);

        $first->update(['slug' => 'jbl-flip-seven-speaker']);
        $third = $this->product(['slug' => self::LONG_SLUG]);

        $this->assertNotContains($third->slug, [self::SHORT_SLUG, $second->slug]);
        $this->assertLessThanOrEqual(55, strlen($third->slug));
        $this->assertDatabaseHas('product_slug_aliases', [
            'product_id' => $first->id,
            'slug' => self::SHORT_SLUG,
        ]);
        $this->get('/product/'.self::SHORT_SLUG)
            ->assertStatus(301)
            ->assertRedirect(route('product.show', $first));
    }

    public function test_product_name_and_price_updates_keep_the_existing_url_stable(): void
    {
        $product = $this->product(['slug' => 'jbl-flip-7']);

        $product->update([
            'name' => 'JBL Flip 7 Updated Product Name With More Specifications',
            'regular_price' => 12000,
            'sale_price' => 11000,
        ]);

        $this->assertSame('jbl-flip-7', $product->fresh()->slug);
        $this->assertDatabaseCount('product_slug_aliases', 0);
    }

    public function test_every_previous_url_redirects_directly_to_the_latest_url(): void
    {
        $product = $this->product(['slug' => 'original-speaker']);
        $product->update(['slug' => 'updated-speaker']);
        $product->update(['slug' => self::LONG_SLUG]);

        foreach (['original-speaker', 'updated-speaker'] as $oldSlug) {
            $this->get('/product/'.$oldSlug)
                ->assertStatus(301)
                ->assertRedirect(route('product.show', $product));
        }

        $this->assertSame(self::SHORT_SLUG, $product->slug);
        $this->get(route('product.show', $product))->assertOk();
    }

    public function test_redirects_preserve_tracking_and_duplicate_query_parameters_exactly(): void
    {
        $product = $this->product(['slug' => 'original-speaker']);
        $product->update(['slug' => 'short-speaker']);
        $query = 'utm_source=facebook&tag=red&tag=blue&search=portable%20speaker&fbclid=abc%2Fdef';

        $this->get('/product/original-speaker?'.$query)
            ->assertStatus(301)
            ->assertRedirect(route('product.show', $product).'?'.$query);

        $this->get('/product/'.$product->id.'?'.$query)
            ->assertStatus(301)
            ->assertRedirect(route('product.show', $product).'?'.$query);
    }

    public function test_unpublished_and_missing_products_never_redirect_or_render(): void
    {
        $product = $this->product(['slug' => 'draft-speaker', 'is_published' => false]);
        $product->update(['slug' => 'updated-draft-speaker']);

        foreach (['draft-speaker', $product->slug, (string) $product->id, 'missing-speaker'] as $slug) {
            $this->get('/product/'.$slug)->assertNotFound();
        }
    }

    public function test_historical_slugs_resolve_for_review_posts_and_admin_id_binding_still_works(): void
    {
        $product = $this->product(['slug' => 'original-speaker']);
        $product->update(['slug' => 'short-speaker']);

        $bound = (new Product)->resolveRouteBinding('original-speaker');
        $adminBound = (new Product)->resolveRouteBinding((string) $product->id, 'id');

        $this->assertSame($product->id, $bound?->id);
        $this->assertSame($product->id, $adminBound?->id);
        $this->actingAs(User::factory()->create())
            ->post('/product/original-speaker/reviews', [
                'rating' => 5,
                'body' => 'The historical product URL still resolves correctly.',
            ])
            ->assertRedirect(route('product.show', $product).'#reviews');
        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'body' => 'The historical product URL still resolves correctly.',
        ]);
    }

    public function test_existing_catalog_is_shortened_automatically_with_collision_safe_aliases(): void
    {
        $category = $this->category();
        $existingShort = $this->product(['slug' => self::SHORT_SLUG]);
        $publishedId = DB::table('products')->insertGetId([
            'category_id' => $category->id,
            'name' => 'JBL Flip 7 With Full Specifications',
            'slug' => self::LONG_SLUG,
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $draftOldSlug = 'sony-portable-wireless-bluetooth-speaker-with-bass-boost-and-long-battery-ds-987-abcdef';
        $draftId = DB::table('products')->insertGetId([
            'category_id' => $category->id,
            'name' => 'Sony Speaker Draft',
            'slug' => $draftOldSlug,
            'is_published' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_17_000002_shorten_product_slugs.php');
        $migration->up();
        $published = Product::findOrFail($publishedId);
        $draft = Product::findOrFail($draftId);

        $this->assertSame(self::SHORT_SLUG, $existingShort->fresh()->slug);
        $this->assertNotSame(self::SHORT_SLUG, $published->slug);
        $this->assertStringStartsWith(self::SHORT_SLUG.'-', $published->slug);
        $this->assertLessThanOrEqual(55, strlen($published->slug));
        $this->assertSame('sony-portable-wireless-bluetooth-speaker-with-bass', $draft->slug);
        $this->assertSame('JBL Flip 7 With Full Specifications', $published->name);
        $this->assertDatabaseHas('product_slug_aliases', ['product_id' => $publishedId, 'slug' => self::LONG_SLUG]);
        $this->assertDatabaseHas('product_slug_aliases', ['product_id' => $draftId, 'slug' => $draftOldSlug]);
        $this->get('/product/'.self::LONG_SLUG)
            ->assertStatus(301)
            ->assertRedirect(route('product.show', $published));
        $this->get('/product/'.$draftOldSlug)->assertNotFound();

        $migration->up();

        $this->assertSame($published->slug, $published->fresh()->slug);
        $this->assertSame($draft->slug, $draft->fresh()->slug);
        $this->assertDatabaseCount('product_slug_aliases', 2);
    }

    public function test_product_metadata_exposes_the_short_absolute_canonical_without_tracking(): void
    {
        $product = $this->product(['slug' => self::LONG_SLUG]);
        $canonical = route('product.show', $product);

        $this->get($canonical.'?utm_source=whatsapp&reviews_page=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Storefront/Product')
                ->where('product.slug', self::SHORT_SLUG)
                ->where('seo.canonical', $canonical));
    }

    public function test_initial_html_contains_product_specific_social_metadata_for_crawlers(): void
    {
        $product = $this->product([
            'slug' => self::LONG_SLUG,
            'meta_title' => 'JBL Flip 7 Speaker & Accessories',
            'meta_description' => 'A portable speaker for music outdoors.',
        ]);
        $imageUrl = 'https://images.example.test/jbl-flip-7.jpg';
        ProductImage::create(['product_id' => $product->id, 'path' => $imageUrl, 'is_primary' => true]);
        $canonical = route('product.show', $product);
        $response = $this->get($canonical.'?utm_source=facebook')->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);

        $this->assertSame(1, $xpath->query('//link[@rel="canonical"]')->length);
        $this->assertSame($canonical, $xpath->evaluate('string(//link[@rel="canonical"]/@href)'));
        $this->assertSame($canonical, $xpath->evaluate('string(//meta[@property="og:url"]/@content)'));
        $this->assertSame($product->meta_title, $xpath->evaluate('string(//title)'));
        $this->assertSame($product->meta_title, $xpath->evaluate('string(//meta[@property="og:title"]/@content)'));
        $this->assertSame($product->meta_description, $xpath->evaluate('string(//meta[@name="description"]/@content)'));
        $this->assertSame(image_url($imageUrl, $product->slug), $xpath->evaluate('string(//meta[@property="og:image"]/@content)'));
        $this->assertSame('product', $xpath->evaluate('string(//meta[@property="og:type"]/@content)'));
    }

    public function test_sitemap_uses_current_urls_and_refreshes_after_slug_or_visibility_changes(): void
    {
        $product = $this->product(['slug' => 'original-speaker']);
        $oldUrl = route('product.show', $product);

        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.$oldUrl.'</loc>', false);

        $product->update(['slug' => self::LONG_SLUG]);
        $newUrl = route('product.show', $product);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.$newUrl.'</loc>', false)
            ->assertDontSee('<loc>'.$oldUrl.'</loc>', false);

        $product->update(['is_published' => false]);

        $this->get('/sitemap.xml')->assertOk()->assertDontSee('<loc>'.$newUrl.'</loc>', false);
    }

    private function category(): Category
    {
        return Category::firstOrCreate(['slug' => 'speakers'], ['name' => 'Speakers', 'is_active' => true]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $this->category()->id,
            'name' => 'Portable Speaker',
            'regular_price' => 10000,
            'stock_quantity' => 5,
            'is_published' => true,
        ], $attributes));
    }
}

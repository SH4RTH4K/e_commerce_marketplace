<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StorefrontMerchandisingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Setting::put('seo_keyword_consistency_enabled', '0');
    }

    public function test_homepage_categories_promote_stocked_products_without_removing_unavailable_pages(): void
    {
        Setting::put('storefront_template', 'template-2');
        Setting::put('homepage_category_product_order', 'newest');
        $category = $this->category();
        $available = $this->product($category, ['stock_quantity' => 2]);
        $unavailable = $this->product($category, ['stock_quantity' => 0]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('templateTwoCategorySections.0.products.0.id', $available->id)
            ->where('templateTwoCategorySections.0.products.1.id', $unavailable->id));
        $this->get(route('product.show', $unavailable))->assertOk();
    }

    public function test_overview_and_flash_sale_prioritize_stock_before_the_existing_sort_order(): void
    {
        Setting::put('storefront_template', 'template-1');
        Setting::put('homepage_product_overview_order', 'newest');
        $category = $this->category();
        $unavailable = $this->product($category, ['stock_quantity' => 0, 'flash_sale_position' => 0]);
        $available = $this->product($category, ['stock_quantity' => 2, 'flash_sale_position' => 10, 'created_at' => now()->subDay()]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('trending.0.id', $available->id)->where('trending.1.id', $unavailable->id)
            ->where('newArrivals.0.id', $available->id)->where('bestSellers.0.id', $available->id)
            ->where('flashProducts.0.id', $available->id));
    }

    #[DataProvider('loadMorePaths')]
    public function test_load_more_keeps_stocked_items_ahead_of_unavailable_items(string $path): void
    {
        $category = $this->category();
        $available = $this->product($category, ['stock_quantity' => 2]);
        $unavailable = $this->product($category, ['stock_quantity' => 0]);
        $separator = str_contains($path, '?') ? '&' : '?';

        $this->get($path.$separator.'category_id='.$category->id)->assertOk()
            ->assertJsonPath('products.0.id', $available->id)
            ->assertJsonPath('products.1.id', $unavailable->id);
    }

    public static function loadMorePaths(): array
    {
        return [
            'category' => ['/home/category-products/load-more'],
            'overview' => ['/home/products/load-more?tab=featured'],
            'flash sale' => ['/home/flash-sale/load-more'],
        ];
    }

    public function test_banners_use_canonical_sku_urls_and_only_promote_available_products(): void
    {
        $category = $this->category();
        $available = $this->product($category, ['stock_quantity' => 2, 'sku' => 'SP-01']);
        $unavailable = $this->product($category, ['stock_quantity' => 0]);
        Banner::create(['title' => 'Available', 'placement' => 'hero', 'product_id' => $available->id, 'is_active' => true]);
        Banner::create(['title' => 'Unavailable', 'placement' => 'hero', 'product_id' => $unavailable->id, 'is_active' => true]);
        Banner::create(['title' => 'Collection', 'placement' => 'hero', 'link_url' => '/shop', 'is_active' => true]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('heroBanners', 2)->where('heroBanners.0.link', '/product/'.$available->url_key));
        $this->get('/product/'.$available->slug)->assertRedirect(route('product.show', $available));
    }

    public function test_speaker_guides_link_only_to_available_published_models_and_appear_in_the_sitemap(): void
    {
        Setting::put('seo_fallback_h1_enabled', '1');
        $category = $this->category();
        $available = $this->product($category, ['stock_quantity' => 2]);
        $this->product($category, ['stock_quantity' => 0]);
        $this->product($category, ['stock_quantity' => 3, 'is_published' => false]);

        $this->get('/blog/bluetooth-speaker-buying-guide-bangladesh')->assertOk()
            ->assertSee('<h1 data-seo-fallback-h1>How to choose a Bluetooth speaker in Bangladesh</h1>', false)
            ->assertSee('Set a budget for the complete order')
            ->assertSee('href="'.route('product.show', $available).'"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->has('guideProducts', 1)->where('guideProducts.0.url', route('product.show', $available))
                ->where('guideCategory.url', route('shop.category', $category)));
        $this->get('/sitemaps/blogs.xml')->assertOk()
            ->assertSee(route('blog.detail', 'bluetooth-speaker-buying-guide-bangladesh'), false)
            ->assertSee(route('blog.detail', 'bluetooth-speaker-battery-waterproof-guide'), false);
        $available->update(['stock_quantity' => 0]);
        $this->get('/blog/bluetooth-speaker-battery-waterproof-guide')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('guideProducts', 0));
    }

    private function category(): Category
    {
        return Category::create(['name' => 'Bluetooth Speakers', 'slug' => 'bluetooth-speaker', 'is_active' => true, 'show_in_menu' => true]);
    }

    private function product(Category $category, array $attributes = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $category->id, 'name' => 'Portable Speaker', 'regular_price' => 100,
            'is_published' => true, 'is_featured' => true, 'is_new_arrival' => true,
            'is_best_seller' => true, 'is_flash_sale' => true,
        ], $attributes));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_homepage_banner_can_link_to_a_published_product(): void
    {
        $category = Category::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'is_active' => true,
            'show_in_menu' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Featured Product',
            'slug' => 'featured-product',
            'is_published' => true,
        ]);
        Banner::create([
            'title' => 'Featured Product Banner',
            'placement' => 'hero',
            'style' => 'brand',
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('heroBanners.0.product.slug', 'featured-product')
            ->where('heroBanners.0.link', '/product/featured-product'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
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

    #[Test]
    public function media_manager_identifies_images_already_used_by_banners(): void
    {
        $image = ProductImage::create([
            'path' => 'uploads/media/hero-image.jpg',
            'alt' => 'Hero image',
        ]);
        $banner = Banner::create([
            'title' => 'Existing hero slide',
            'image' => $image->path,
            'placement' => 'hero',
            'position' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/media');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('images.data.0.id', $image->id)
            ->where('images.data.0.banner_usage.0.id', $banner->id)
            ->where('images.data.0.banner_usage.0.placement', 'hero')
            ->where('images.data.0.banner_usage.0.position', 3)
            ->where('images.data.0.banner_usage.0.is_active', true));
    }

    #[Test]
    public function media_manager_does_not_create_duplicate_banners_for_the_same_placement(): void
    {
        $image = ProductImage::create([
            'path' => 'uploads/media/reused-image.jpg',
            'alt' => 'Reused image',
        ]);
        Banner::create([
            'title' => 'Existing hero slide',
            'image' => $image->path,
            'placement' => 'hero',
            'position' => 1,
            'is_active' => false,
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/media/banners', [
                'image_ids' => [$image->id],
                'placement' => 'hero',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('image_ids');
        $this->assertDatabaseCount('banners', 1);
    }

    #[Test]
    public function media_manager_can_filter_images_by_banner_placement(): void
    {
        $heroImage = ProductImage::create(['path' => 'uploads/media/hero.jpg', 'alt' => 'Hero']);
        $middleImage = ProductImage::create(['path' => 'uploads/media/middle.jpg', 'alt' => 'Middle']);
        ProductImage::create(['path' => 'uploads/media/unused.jpg', 'alt' => 'Unused']);

        Banner::create(['image' => $heroImage->path, 'placement' => 'hero']);
        Banner::create(['image' => $middleImage->path, 'placement' => 'middle']);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/media?banner_usage=hero')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('bannerUsageFilter', 'hero')
                ->has('images.data', 1)
                ->where('images.data.0.id', $heroImage->id));

        $this->actingAs($admin)->get('/admin/media?banner_usage=middle')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('bannerUsageFilter', 'middle')
                ->has('images.data', 1)
                ->where('images.data.0.id', $middleImage->id));
    }
}

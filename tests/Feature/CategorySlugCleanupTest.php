<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySlugCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_category_slugs_redirect_permanently_to_the_approved_slugs(): void
    {
        foreach ([
            'watch' => 'watches',
            'airpod' => 'airpods',
            'others' => 'miscellaneous-products',
        ] as $oldSlug => $newSlug) {
            $this->get('/category/'.$oldSlug)
                ->assertMovedPermanently()
                ->assertRedirect('/category/'.$newSlug);
        }
    }

    public function test_category_navigation_and_sitemap_use_the_new_slugs(): void
    {
        $categories = collect([
            ['name' => 'Watches', 'slug' => 'watches'],
            ['name' => 'AirPods', 'slug' => 'airpods'],
            ['name' => 'Miscellaneous Products', 'slug' => 'miscellaneous-products'],
        ])->map(fn (array $attributes): Category => Category::create($attributes + [
            'is_active' => true,
            'show_in_menu' => true,
        ]));

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('/category/watches', $home);
        $this->assertStringContainsString('/category/airpods', $home);
        $this->assertStringContainsString('/category/miscellaneous-products', $home);
        $this->assertStringNotContainsString('href="/category/watch"', $home);
        $this->assertStringNotContainsString('href="/category/airpod"', $home);
        $this->assertStringNotContainsString('href="/category/others"', $home);

        $sitemap = $this->get('/sitemaps/categories.xml')->assertOk()->getContent();
        foreach ($categories as $category) {
            $this->assertStringContainsString(route('shop.category', $category), $sitemap);
        }
        $this->assertStringNotContainsString('/category/watch</loc>', $sitemap);
        $this->assertStringNotContainsString('/category/airpod</loc>', $sitemap);
        $this->assertStringNotContainsString('/category/others</loc>', $sitemap);
    }
}

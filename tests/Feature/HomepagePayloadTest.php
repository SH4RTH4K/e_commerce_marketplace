<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepagePayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Setting::put('storefront_template', 'template-2');
        Setting::put('homepage_category_sections_enabled', '1');
        Setting::put('homepage_category_sections_limit', '6');
        Setting::put('seo_keyword_consistency_enabled', '0');

        for ($categoryIndex = 1; $categoryIndex <= 6; $categoryIndex++) {
            $category = Category::create([
                'name' => 'Category '.$categoryIndex,
                'slug' => 'category-'.$categoryIndex,
                'is_active' => true,
                'show_in_menu' => true,
            ]);

            for ($productIndex = 1; $productIndex <= 10; $productIndex++) {
                Product::create([
                    'category_id' => $category->id,
                    'name' => "Product {$categoryIndex}-{$productIndex}",
                    'slug' => "product-{$categoryIndex}-{$productIndex}",
                    'regular_price' => 100,
                    'stock_quantity' => 5,
                    'is_published' => true,
                ]);
            }
        }
    }

    public function test_compact_homepage_payload_stays_under_the_budget(): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        $this->assertLessThan(150 * 1024, strlen($content));
        $this->assertStringNotContainsString('purchase_price', $content);
        $this->assertStringNotContainsString('supplierLinks', $content);
    }
}

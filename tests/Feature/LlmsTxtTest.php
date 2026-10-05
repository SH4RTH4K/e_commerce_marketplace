<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LlmsTxtTest extends TestCase
{
    use RefreshDatabase;

    public function test_llms_txt_returns_ai_crawler_guidance(): void
    {
        Setting::put('site_name', 'Taqi Life');
        Setting::put('default_meta_description', 'Premium lifestyle marketplace.');
        Setting::put('seo_llms_txt_enabled', '1');
        Setting::put('seo_llms_txt_notes', 'Use product pages for live stock and delivery details.');
        $category = Category::create(['name' => 'Gadgets Electronics', 'slug' => 'gadgets-electronics', 'is_active' => true, 'show_in_menu' => true]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Smart Watch',
            'regular_price' => 2500,
            'stock_quantity' => 5,
            'is_published' => true,
        ]);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('# Taqi Life', false)
            ->assertSee('Sitemap: ' . route('sitemap'), false)
            ->assertSee('Robots: ' . route('robots'), false)
            ->assertSee('Gadgets Electronics', false)
            ->assertSee('Smart Watch', false)
            ->assertSee('Use product pages for live stock and delivery details.', false);
    }

    public function test_llms_txt_can_be_disabled(): void
    {
        Setting::put('seo_llms_txt_enabled', '0');

        $this->get('/llms.txt')->assertNotFound();
    }
}

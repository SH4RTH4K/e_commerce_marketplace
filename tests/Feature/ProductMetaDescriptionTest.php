<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMetaDescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Setting::put('site_name', 'TAQI LIFE');
        Setting::put('seo_keyword_consistency_enabled', '0');
    }

    public function test_ten_product_pages_use_unique_attribute_based_meta_descriptions(): void
    {
        $category = Category::create(['name' => 'Travel Accessories', 'slug' => 'travel-accessories', 'is_active' => true]);
        $products = collect();

        for ($index = 1; $index <= 10; $index++) {
            $product = Product::create([
                'category_id' => $category->id,
                'name' => "Canvas Travel Organizer {$index}",
                'sku' => "TRAVEL-{$index}",
                'regular_price' => 900 + $index,
                'description' => '<p>Lightweight organizer with two zipped compartments.</p>',
                'specifications' => [
                    ['label' => 'Material', 'value' => 'Canvas'],
                    ['label' => 'Feature', 'value' => 'Two zipped compartments'],
                ],
                'is_published' => true,
            ]);
            $product->variants()->create(['type' => 'Color', 'value' => 'Navy', 'stock' => 5]);
            $product->variants()->create(['type' => 'Size', 'value' => 'Medium', 'stock' => 5]);
            $products->push($product);
        }

        $descriptions = $products->shuffle()->map(function (Product $product): string {
            $html = $this->get(route('product.show', $product))->assertOk()->getContent();
            $xpath = $this->xpath($html);
            $description = $xpath->evaluate('string(//meta[@name="description"]/@content)');
            $this->assertSame($description, $xpath->evaluate('string(//meta[@property="og:description"]/@content)'));
            $this->assertSame($description, $xpath->evaluate('string(//meta[@name="twitter:description"]/@content)'));
            $this->assertLessThanOrEqual(160, mb_strlen($description));
            $this->assertGreaterThanOrEqual(140, mb_strlen($description));
            $this->assertStringContainsString($product->name, $description);
            $this->assertStringContainsString('Travel Accessories', $description);
            $this->assertStringContainsString(money($product->price), $description);
            $this->assertStringContainsString('Delivery across Bangladesh.', $description);
            $this->assertStringNotContainsString('View product details and availability', $description);

            return $description;
        });

        $this->assertCount(10, $descriptions->unique());
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return new DOMXPath($document);
    }
}

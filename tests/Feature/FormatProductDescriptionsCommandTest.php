<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormatProductDescriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_every_saved_product_description(): void
    {
        $category = Category::create(['name' => 'Jewellery', 'slug' => 'jewellery']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Service Benefits',
            'slug' => 'service-benefits',
            'description' => '100% Authentic Satisfied Product100% Money Back Refund Policy10 Days Easy Return & Replace Policy',
        ]);
        $richTextProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Rich Text',
            'slug' => 'rich-text',
            'description' => '<h2>Details</h2><p>Already formatted.</p>',
        ]);

        $this->artisan('products:format-descriptions')
            ->expectsOutput('Checked 2 product descriptions; 1 updated.')
            ->assertSuccessful();

        $this->assertSame(
            '<p>100% Authentic Satisfied Product</p><p>100% Money Back Refund Policy</p><p>10 Days Easy Return &amp; Replace Policy</p>',
            $product->fresh()->description,
        );
        $this->assertSame('<h2>Details</h2><p>Already formatted.</p>', $richTextProduct->fresh()->description);

        $this->artisan('products:format-descriptions')
            ->expectsOutput('Checked 2 product descriptions; 0 updated.')
            ->assertSuccessful();
    }

    public function test_dry_run_reports_changes_without_saving_them(): void
    {
        $category = Category::create(['name' => 'Fashion', 'slug' => 'fashion']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Unformatted Product',
            'slug' => 'unformatted-product',
            'description' => 'Product details of Test WatchBrand: OLEVSModel Number: 9931GMovement brand: Quartz',
        ]);

        $this->artisan('products:format-descriptions --dry-run')
            ->expectsOutput('Checked 1 product descriptions; 1 would be updated.')
            ->assertSuccessful();

        $this->assertSame('Product details of Test WatchBrand: OLEVSModel Number: 9931GMovement brand: Quartz', $product->fresh()->description);
    }
}

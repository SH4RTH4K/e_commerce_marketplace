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

    public function test_it_preserves_marketing_copy_that_only_contains_a_few_field_like_phrases(): void
    {
        $category = Category::create(['name' => 'Fashion', 'slug' => 'fashion']);
        $description = '<p>Comfortable everyday wear. Fabric: Terry Cotton. Size: M, L, XL. Measurements: see the size guide.</p>';
        Product::create([
            'category_id' => $category->id,
            'name' => 'Marketing Copy',
            'slug' => 'marketing-copy',
            'description' => $description,
        ]);

        $this->artisan('products:format-descriptions --dry-run')
            ->expectsOutput('Checked 1 product descriptions; 0 would be updated.')
            ->assertSuccessful();
    }

    public function test_it_does_not_reformat_an_already_structured_specification_block(): void
    {
        $category = Category::create(['name' => 'Smart Watch', 'slug' => 'smart-watch']);
        $description = '<p><strong>Specification:</strong></p><p><strong>Product Name:</strong> X7 Smart Watch</p><ul><li>Heart rate monitoring.</li><li>Pedometer.</li></ul>';
        Product::create([
            'category_id' => $category->id,
            'name' => 'Structured Specification',
            'slug' => 'structured-specification',
            'description' => $description,
        ]);

        $this->artisan('products:format-descriptions --dry-run')
            ->expectsOutput('Checked 1 product descriptions; 0 would be updated.')
            ->assertSuccessful();
    }
}

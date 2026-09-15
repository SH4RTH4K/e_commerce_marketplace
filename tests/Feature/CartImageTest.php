<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartImageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cart_items_keep_the_original_external_image_url(): void
    {
        $category = Category::create([
            'name' => 'Jewellery',
            'slug' => 'jewellery',
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Gold Plated Jewelry Set',
            'slug' => 'gold-plated-jewelry-set',
            'stock_quantity' => 1,
            'is_published' => true,
        ]);
        $imageUrl = 'https://supplier.example.test/images/jewelry-set.jpg';
        ProductImage::create([
            'product_id' => $product->id,
            'path' => $imageUrl,
            'is_primary' => true,
        ]);

        $this->withSession([
            'cart' => ["{$product->id}|" => [
                'product_id' => $product->id,
                'qty' => 1,
                'variant' => null,
            ]],
        ]);

        $item = app(CartService::class)->items()->sole();

        $this->assertSame($imageUrl, $item->image);
        $this->assertStringNotContainsString('wsrv.nl', $item->image);
    }
}

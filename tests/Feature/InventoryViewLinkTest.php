<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InventoryViewLinkTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function inventory_results_include_the_storefront_product_slug(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Test category', 'slug' => 'test-category', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Linked product', 'slug' => 'linked-product', 'stock_quantity' => 10, 'is_published' => true]);

        $this->actingAs($admin)
            ->get('/admin/inventory')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Inventory/Index')
                ->where('products.data.0.slug', $product->slug));

        $this->get('/product/'.$product->slug)->assertOk();
    }
}

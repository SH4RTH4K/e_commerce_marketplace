<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InventoryFilterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function low_stock_filter_uses_the_stock_level_query_parameter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Test category', 'slug' => 'test-category', 'is_active' => true]);

        Product::create(['category_id' => $category->id, 'name' => 'Low stock item', 'slug' => 'low-stock-item', 'stock_quantity' => 3, 'is_published' => true]);
        Product::create(['category_id' => $category->id, 'name' => 'Out of stock item', 'slug' => 'out-of-stock-item', 'stock_quantity' => 0, 'is_published' => true]);
        Product::create(['category_id' => $category->id, 'name' => 'In stock item', 'slug' => 'in-stock-item', 'stock_quantity' => 20, 'is_published' => true]);

        $this->actingAs($admin)
            ->get('/admin/inventory?category=&q=&stock_level=low')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Inventory/Index')
                ->where('stock_level', 'low')
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Low stock item'));
    }

    #[Test]
    public function inventory_can_be_ordered_by_name_descending(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Test category', 'slug' => 'test-category', 'is_active' => true]);

        Product::create(['category_id' => $category->id, 'name' => 'Alpha item', 'slug' => 'alpha-item', 'stock_quantity' => 10, 'is_published' => true]);
        Product::create(['category_id' => $category->id, 'name' => 'Zulu item', 'slug' => 'zulu-item', 'stock_quantity' => 10, 'is_published' => true]);

        $this->actingAs($admin)
            ->get('/admin/inventory?order=name_desc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('order', 'name_desc')
                ->where('products.data.0.name', 'Zulu item'));
    }
}

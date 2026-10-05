<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StorefrontCategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_parent_category_page_includes_products_from_child_and_sub_child_categories(): void
    {
        $parent = Category::create([
            'name' => 'Gadgets & Electronics',
            'slug' => 'gadgets-electronics',
            'menu_order' => 10,
            'is_active' => true,
            'show_in_menu' => true,
        ]);
        $child = Category::create([
            'parent_id' => $parent->id,
            'name' => 'Audio',
            'slug' => 'audio',
            'menu_order' => 20,
            'is_active' => true,
            'show_in_menu' => true,
        ]);
        $subChild = Category::create([
            'parent_id' => $child->id,
            'name' => 'Airpod',
            'slug' => 'airpod',
            'menu_order' => 30,
            'is_active' => true,
            'show_in_menu' => true,
        ]);

        $parentProduct = $this->product($parent, 'Main Gadget');
        $childProduct = $this->product($child, 'Audio Product');
        $subChildProduct = $this->product($subChild, 'Airpod Product');
        $unrelated = Category::create([
            'name' => 'Home',
            'slug' => 'home',
            'menu_order' => 40,
            'is_active' => true,
            'show_in_menu' => true,
        ]);
        $this->product($unrelated, 'Home Product');

        $this->get('/category/gadgets-electronics')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Storefront/Shop')
                ->where('activeCategory.id', $parent->id)
                ->where('products.total', 3)
                ->has('products.data', 3)
                ->where('categories.0.products_count', 3)
                ->where('products.data.0.id', $subChildProduct->id)
                ->where('products.data.1.id', $childProduct->id)
                ->where('products.data.2.id', $parentProduct->id));
    }

    private function product(Category $category, string $name): Product
    {
        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'regular_price' => 1000,
            'stock_quantity' => 5,
            'is_published' => true,
        ]);
    }
}

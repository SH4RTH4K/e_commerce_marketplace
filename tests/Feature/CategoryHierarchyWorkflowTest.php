<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CategoryHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryHierarchyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function tree(): array
    {
        $root = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $child = Category::create(['name' => 'Audio', 'slug' => 'audio', 'parent_id' => $root->id]);
        $leaf = Category::create(['name' => 'Earbuds', 'slug' => 'earbuds', 'parent_id' => $child->id]);

        return [$root, $child, $leaf];
    }

    private function product(Category $category): Product
    {
        return Product::create(['name' => $category->name.' product', 'category_id' => $category->id, 'regular_price' => 100, 'stock_quantity' => 2, 'is_published' => true]);
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_every_layer_is_browsable_with_its_own_products_and_descendants(): void
    {
        [$root, $child, $leaf] = $this->tree();
        foreach ([$root, $child, $leaf] as $category) {
            $this->product($category);
        }
        foreach ([[$root, 3], [$child, 2], [$leaf, 1]] as [$category, $total]) {
            $this->get('/category/'.$category->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('activeCategory.id', $category->id)->where('products.total', $total));
        }
        $this->get('/category/audio')->assertInertia(fn (Assert $page) => $page
            ->where('categoryPath.0.slug', 'electronics')->where('categoryPath.1.slug', 'audio')
            ->has('browseCategories', 1)->where('browseCategories.0.slug', 'earbuds'));
        $this->get('/category/earbuds')->assertInertia(fn (Assert $page) => $page
            ->has('categoryPath', 3)->has('browseCategories', 0));
    }

    public function test_inactive_branch_is_not_browsable_or_counted_but_menu_hidden_active_branch_keeps_products_in_parent(): void
    {
        [$root, $child, $leaf] = $this->tree();
        $this->product($root);
        $this->product($leaf);
        $child->update(['show_in_menu' => false]);
        $this->get('/category/electronics')->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 2)->where('filterCategories.0.products_count', 2)->has('browseCategories', 0));
        $this->get('/category/earbuds')->assertOk();
        $child->update(['is_active' => false]);
        $this->get('/category/earbuds')->assertNotFound();
        $this->get('/category/electronics')->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)->where('filterCategories.0.products_count', 1));
    }

    public function test_admin_can_create_all_three_levels_and_assign_products_to_each(): void
    {
        $this->admin();
        $parent = null;
        foreach (['Main', 'Sub', 'Sub sub'] as $index => $name) {
            $this->post('/admin/categories', ['name' => $name, 'parent_id' => $parent?->id, 'is_active' => true, 'show_in_menu' => true])->assertSessionHasNoErrors();
            $parent = Category::where('name', $name)->firstOrFail();
            $this->post('/admin/products', ['name' => $name.' item', 'category_id' => $parent->id, 'regular_price' => 100, 'stock_quantity' => 2])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('products', ['name' => $name.' item', 'category_id' => $parent->id]);
        }
        $this->get('/admin/products/create')->assertInertia(fn (Assert $page) => $page
            ->where('categories.2.depth', 3)->where('categories.2.label', 'Main / Sub / Sub sub'));
    }

    public function test_home_sections_roll_up_descendants_and_product_breadcrumbs_show_the_full_path(): void
    {
        [$root, $child, $leaf] = $this->tree();
        $product = $this->product($leaf);
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('featuredCategories', 1)->where('featuredCategories.0.products_count', 1)
            ->has('templateTwoCategorySections', 1)->where('templateTwoCategorySections.0.products.0.id', $product->id));
        $this->get('/home/category-products/load-more?category_id='.$root->id)->assertOk()->assertJsonPath('products.0.id', $product->id);
        $this->get('/product/'.$product->url_key)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('categoryPath', 3)->where('categoryPath.2.slug', 'earbuds'));
    }

    public function test_self_parent_cycles_fourth_level_and_moves_that_deepen_a_subtree_are_rejected(): void
    {
        $this->admin();
        [$root, $child, $leaf] = $this->tree();
        foreach ([$root->id, $child->id, $leaf->id] as $parentId) {
            $this->put('/admin/categories/'.$root->id, ['name' => $root->name, 'parent_id' => $parentId])->assertSessionHasErrors('parent_id');
        }
        $this->post('/admin/categories', ['name' => 'Fourth level', 'parent_id' => $leaf->id])->assertSessionHasErrors('parent_id');
        $other = Category::create(['name' => 'Other', 'slug' => 'other']);
        $this->put('/admin/categories/'.$root->id, ['name' => $root->name, 'parent_id' => $other->id])->assertSessionHasErrors('parent_id');
        $this->assertDatabaseHas('categories', ['id' => $root->id, 'parent_id' => null]);
        $this->get('/admin/categories/'.$root->id.'/edit')->assertInertia(fn (Assert $page) => $page->has('parents', 0));
    }

    public function test_moving_a_valid_branch_updates_paths_and_browsing(): void
    {
        $this->admin();
        [$root, $child, $leaf] = $this->tree();
        $product = $this->product($leaf);
        $other = Category::create(['name' => 'Other', 'slug' => 'other']);
        $this->put('/admin/categories/'.$child->id, ['name' => 'Audio', 'parent_id' => $other->id, 'is_active' => true, 'show_in_menu' => true])->assertSessionHasNoErrors();
        $this->get('/category/electronics')->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
        $this->get('/category/other')->assertInertia(fn (Assert $page) => $page->where('products.total', 1)->where('products.data.0.id', $product->id));
        $this->get('/category/earbuds')->assertInertia(fn (Assert $page) => $page->where('categoryPath.0.slug', 'other'));
    }

    public function test_category_delete_cannot_cascade_delete_products_or_detach_children(): void
    {
        $this->admin();
        [$root, $child, $leaf] = $this->tree();
        $product = $this->product($leaf);
        $this->delete('/admin/categories/'.$root->id)->assertSessionHasErrors('category');
        $this->delete('/admin/categories/'.$leaf->id)->assertSessionHasErrors('category');
        $empty = Category::create(['name' => 'Empty', 'slug' => 'empty']);
        $this->post('/admin/categories/bulk', ['ids' => [$leaf->id, $empty->id], 'bulk_action' => 'delete'])->assertSessionHasErrors('category');
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('categories', ['id' => $empty->id]);
        $this->delete('/admin/categories/'.$empty->id)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('categories', ['id' => $empty->id]);
    }

    public function test_admin_branch_filter_includes_descendants_and_leaf_filter_is_exact(): void
    {
        $this->admin();
        [$root, $child, $leaf] = $this->tree();
        foreach ([$root, $child, $leaf] as $category) {
            $this->product($category);
        }
        $this->get('/admin/products?category='.$root->id)->assertInertia(fn (Assert $page) => $page->where('products.total', 3));
        $this->get('/admin/products?category='.$leaf->id)->assertInertia(fn (Assert $page) => $page->where('products.total', 1));
        $this->get('/admin/categories/create?parent_id='.$child->id)->assertInertia(fn (Assert $page) => $page->where('category.parent_id', $child->id));
    }

    public function test_product_counts_use_two_queries_and_options_tolerate_legacy_cycles(): void
    {
        [$root, $child, $leaf] = $this->tree();
        $this->product($leaf);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $tree = Category::storefrontFilterTree();
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(1, $tree[0]['products_count']);
        $this->assertSame(1, $tree[0]['children'][0]['children'][0]['products_count']);
        $root->update(['parent_id' => $leaf->id]);
        $options = app(CategoryHierarchyService::class)->options();
        $this->assertCount(3, $options);
        $this->assertFalse($options[0]['hierarchy_valid']);
        $this->assertSame([], Category::storefrontMenuTree());
    }
}

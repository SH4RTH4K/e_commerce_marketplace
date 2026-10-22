<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(Category $category, array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Formal Shirt',
            'category_id' => $category->id,
            'regular_price' => 500,
            'stock_quantity' => 10,
            'is_published' => true,
            'source_type' => 'manual',
        ], $attributes));
    }

    public function test_shirt_search_still_includes_t_shirts_without_category_exclusions(): void
    {
        $shirts = Category::create(['name' => 'Shirt', 'slug' => 'shirt']);
        $tShirts = Category::create(['name' => 'T-Shirt', 'slug' => 't-shirt']);
        $tShirt = $this->product($tShirts, ['name' => 'Basic T-Shirt']);
        $shirt = $this->product($shirts);

        $this->get('/admin/products?q=Shirt&sort=name')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'Shirt')
            ->where('filters.category_scope', 'all')
            ->where('filters.exclude_category', '')
            ->has('products.data', 2)
            ->where('products.data.0.id', $tShirt->id)
            ->where('products.data.1.id', $shirt->id));
    }

    public function test_excluding_a_category_hides_its_entire_branch_including_inactive_descendants_only(): void
    {
        $men = Category::create(['name' => 'Men', 'slug' => 'men']);
        $tShirts = Category::create(['name' => 'T-Shirt', 'slug' => 'men-t-shirt', 'parent_id' => $men->id]);
        $printed = Category::create(['name' => 'Printed', 'slug' => 'printed', 'parent_id' => $tShirts->id, 'is_active' => false]);
        $women = Category::create(['name' => 'Women', 'slug' => 'women']);
        $sameName = Category::create(['name' => 'T-Shirt', 'slug' => 'women-t-shirt', 'parent_id' => $women->id]);
        $this->product($tShirts, ['name' => 'Basic T-Shirt']);
        $this->product($printed, ['name' => 'Printed T-Shirt']);
        $shirt = $this->product($men, ['name' => 'Formal Shirt']);
        $unrelatedTShirt = $this->product($sameName, ['name' => 'Women T-Shirt']);

        $this->get('/admin/products?q=Shirt&sort=name&exclude_category='.$tShirts->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.exclude_category', $tShirts->id)
            ->has('products.data', 2)
            ->where('products.data.0.id', $shirt->id)
            ->where('products.data.1.id', $unrelatedTShirt->id)
            ->where('summary.all', 2));
    }

    public function test_direct_category_scope_lists_only_products_assigned_to_the_selected_category(): void
    {
        $men = Category::create(['name' => 'Men', 'slug' => 'men']);
        $shirts = Category::create(['name' => 'Shirt', 'slug' => 'shirt', 'parent_id' => $men->id]);
        $formal = Category::create(['name' => 'Formal', 'slug' => 'formal', 'parent_id' => $shirts->id]);
        $direct = $this->product($men);
        $this->product($shirts);
        $this->product($formal);

        $this->get('/admin/products?category='.$men->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.category_scope', 'all')
            ->where('products.total', 3));
        $this->get('/admin/products?category='.$men->id.'&category_scope=direct')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.category_scope', 'direct')
            ->has('products.data', 1)
            ->where('products.data.0.id', $direct->id)
            ->where('summary.all', 1));
    }

    public function test_direct_scope_without_a_source_category_is_normalized_to_all(): void
    {
        $first = Category::create(['name' => 'First', 'slug' => 'first']);
        $second = Category::create(['name' => 'Second', 'slug' => 'second']);
        $this->product($first);
        $this->product($second);

        $this->get('/admin/products?q=Shirt&category_scope=direct')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.category', '')
            ->where('filters.category_scope', 'all')
            ->where('products.total', 2));
    }

    public function test_direct_category_scope_is_retained_when_paginating_the_source_list(): void
    {
        $men = Category::create(['name' => 'Men', 'slug' => 'men']);
        $shirts = Category::create(['name' => 'Shirt', 'slug' => 'shirt', 'parent_id' => $men->id]);
        for ($index = 0; $index < 16; $index++) {
            $this->product($men);
        }
        $this->product($shirts);

        $this->get('/admin/products?category='.$men->id.'&category_scope=direct')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 16)
            ->where('products.next_page_url', function (string $url) use ($men): bool {
                parse_str(parse_url($url, PHP_URL_QUERY), $parameters);

                return ($parameters['category'] ?? null) === (string) $men->id
                    && ($parameters['category_scope'] ?? null) === 'direct';
            }));
        $this->get('/admin/products?category='.$men->id.'&category_scope=direct&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.total', 16));
    }

    public function test_empty_optional_category_filters_are_accepted_and_normalized(): void
    {
        $this->get('/admin/products?category=&category_scope=&exclude_category=')->assertOk()->assertSessionHasNoErrors()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.category', '')
                ->where('filters.category_scope', 'all')
                ->where('filters.exclude_category', ''));
    }

    public function test_exclusion_combines_with_other_filters_and_is_retained_in_normalized_pagination(): void
    {
        $men = Category::create(['name' => 'Men', 'slug' => 'men']);
        $tShirts = Category::create(['name' => 'T-Shirt', 'slug' => 't-shirt', 'parent_id' => $men->id]);
        $women = Category::create(['name' => 'Women', 'slug' => 'women']);
        for ($index = 0; $index < 17; $index++) {
            $this->product($men, ['name' => 'Formal Shirt '.$index]);
        }
        $this->product($men, ['is_published' => false]);
        $this->product($tShirts);
        $this->product($tShirts, ['is_published' => false]);
        $this->product($women);
        $this->product($men, ['source_type' => 'dropshipping']);
        $this->product($men, ['name' => 'Cotton Trousers']);
        $query = [
            'q' => '  Shirt  ',
            'category' => $men->id,
            'category_scope' => 'all',
            'exclude_category' => $tShirts->id,
            'status' => 'active',
            'source' => 'manual',
            'per_page' => 15,
            'unused' => 'ignored',
        ];

        $this->get('/admin/products?'.http_build_query($query))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'Shirt')
            ->where('filters.status', 'published')
            ->where('summary.all', 18)
            ->where('summary.published', 17)
            ->where('summary.draft', 1)
            ->where('products.total', 17)
            ->has('products.data', 15)
            ->where('products.next_page_url', function (string $url) use ($men, $tShirts): bool {
                parse_str(parse_url($url, PHP_URL_QUERY), $parameters);

                return ($parameters['q'] ?? null) === 'Shirt'
                    && ($parameters['status'] ?? null) === 'published'
                    && ($parameters['category'] ?? null) === (string) $men->id
                    && ! isset($parameters['category_scope'])
                    && ($parameters['exclude_category'] ?? null) === (string) $tShirts->id
                    && ($parameters['source'] ?? null) === 'manual'
                    && ($parameters['per_page'] ?? null) === '15'
                    && ! isset($parameters['unused']);
            }));
        $this->get('/admin/products?'.http_build_query([...$query, 'page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('products.total', 17));
    }

    public function test_moving_products_to_a_subcategory_removes_them_from_the_direct_source_list(): void
    {
        $men = Category::create(['name' => 'Men', 'slug' => 'men']);
        $shirts = Category::create(['name' => 'Shirt', 'slug' => 'shirt', 'parent_id' => $men->id]);
        $tShirts = Category::create(['name' => 'T-Shirt', 'slug' => 't-shirt', 'parent_id' => $men->id]);
        $shirt = $this->product($men);
        $tShirt = $this->product($tShirts, ['name' => 'Basic T-Shirt']);
        $list = '/admin/products?'.http_build_query(['q' => 'Shirt', 'category' => $men->id, 'category_scope' => 'direct', 'exclude_category' => $tShirts->id]);
        $this->get($list)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)->where('products.data.0.id', $shirt->id));

        $this->from($list)->post('/admin/products/bulk', [
            'ids' => [$shirt->id],
            'bulk_action' => 'move_category',
            'category_id' => $shirts->id,
        ])->assertSessionHasNoErrors()->assertRedirect($list);

        $this->assertDatabaseHas('products', ['id' => $shirt->id, 'category_id' => $shirts->id]);
        $this->assertDatabaseHas('products', ['id' => $tShirt->id, 'category_id' => $tShirts->id]);
        $this->get($list)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)->where('summary.all', 0));
    }

    #[DataProvider('invalidCategoryFilters')]
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function test_invalid_category_filters_return_422(array $parameters, string $field): void
    {
        $this->getJson('/admin/products?'.http_build_query($parameters))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidCategoryFilters(): array
    {
        return [
            'array exclusion' => [['exclude_category' => [1]], 'exclude_category'],
            'text exclusion' => [['exclude_category' => 'T-Shirt'], 'exclude_category'],
            'zero exclusion' => [['exclude_category' => 0], 'exclude_category'],
            'negative exclusion' => [['exclude_category' => -1], 'exclude_category'],
            'fractional exclusion' => [['exclude_category' => '1.5'], 'exclude_category'],
            'missing category' => [['exclude_category' => 999999], 'exclude_category'],
            'array scope' => [['category_scope' => ['direct']], 'category_scope'],
            'unknown scope' => [['category_scope' => 'exact'], 'category_scope'],
        ];
    }

    public function test_category_filters_still_require_the_product_view_permission(): void
    {
        $category = Category::create(['name' => 'T-Shirt', 'slug' => 't-shirt']);
        $this->actingAs(User::factory()->create(['role' => 'inventory', 'permissions' => []]));

        $this->get('/admin/products?exclude_category='.$category->id.'&category_scope=direct')->assertForbidden();
    }
}

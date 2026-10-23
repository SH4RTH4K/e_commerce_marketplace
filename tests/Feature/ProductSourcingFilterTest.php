<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipSupplier;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductSourcingFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[DataProvider('sourcingSelections')]
    public function test_sourcing_filter_selects_the_saved_switch_independently_of_actual_stock(string $path, string $sorting, string $filterPath, string $sourcing, bool $enabled): void
    {
        $zero = $this->product(['name' => 'A matching zero stock', 'allow_backorder' => $enabled, 'stock_quantity' => 0]);
        $stocked = $this->product(['name' => 'B matching positive stock', 'allow_backorder' => $enabled, 'stock_quantity' => 8]);
        $this->product(['name' => 'C excluded zero stock', 'allow_backorder' => ! $enabled, 'stock_quantity' => 0]);
        $this->product(['name' => 'D excluded positive stock', 'allow_backorder' => ! $enabled, 'stock_quantity' => 8]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get($path.'?'.$sorting.'&sourcing='.$sourcing)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where($filterPath, $sourcing)
                ->has('products.data', 2)->where('products.data.0.id', $zero->id)->where('products.data.1.id', $stocked->id));
    }

    public static function sourcingSelections(): array
    {
        return [
            'catalog enabled' => ['/admin/products', 'sort=name', 'filters.sourcing', 'enabled', true],
            'catalog disabled' => ['/admin/products', 'sort=name', 'filters.sourcing', 'disabled', false],
            'inventory enabled' => ['/admin/inventory', 'order=name_asc', 'sourcing', 'enabled', true],
            'inventory disabled' => ['/admin/inventory', 'order=name_asc', 'sourcing', 'disabled', false],
        ];
    }

    #[DataProvider('listScreens')]
    public function test_clearing_sourcing_filter_restores_both_switch_states(string $path, string $filterPath): void
    {
        $this->product(['allow_backorder' => true]);
        $this->product(['allow_backorder' => false]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get($path.'?sourcing=')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where($filterPath, '')->has('products.data', 2));
    }

    public static function listScreens(): array
    {
        return [
            'catalog' => ['/admin/products', 'filters.sourcing'],
            'inventory' => ['/admin/inventory', 'sourcing'],
        ];
    }

    public function test_catalog_combines_sourcing_with_other_filters_and_scopes_visibility_counts(): void
    {
        $supplier = $this->supplier('catalog-sourcing');
        $attributes = ['name' => 'Filtered Clock', 'supplier_id' => $supplier->id, 'allow_backorder' => true, 'stock_quantity' => 0];
        $matching = $this->product($attributes);
        $this->product([...$attributes, 'is_published' => false]);
        $this->product([...$attributes, 'allow_backorder' => false]);
        $this->product([...$attributes, 'stock_quantity' => 8]);
        $this->product([...$attributes, 'supplier_id' => null]);
        $this->product([...$attributes, 'name' => 'Unrelated Watch']);
        $parameters = ['q' => 'Clock', 'category' => $matching->category_id, 'supplier' => $supplier->id, 'stock' => 'out', 'status' => 'published', 'source' => 'manual', 'sourcing' => 'enabled'];

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/products?'.http_build_query($parameters))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $matching->id)
                ->where('summary.all', 2)->where('summary.published', 1)->where('summary.draft', 1));
    }

    public function test_inventory_summaries_matching_units_and_csv_share_the_sourcing_filter(): void
    {
        $supplier = $this->supplier('inventory-sourcing');
        $attributes = ['name' => 'Filtered Clock', 'supplier_id' => $supplier->id, 'allow_backorder' => true];
        $zero = $this->product([...$attributes, 'sku' => 'SOURCE-ZERO', 'stock_quantity' => 0]);
        $low = $this->product([...$attributes, 'sku' => 'SOURCE-LOW', 'stock_quantity' => 2, 'sourced_stock_quantity' => 1]);
        $this->product([...$attributes, 'sku' => 'SOURCE-HIGH', 'stock_quantity' => 8, 'sourced_stock_quantity' => 2, 'source_type' => 'dropshipping']);
        $this->product([...$attributes, 'sku' => 'SOURCE-DISABLED', 'allow_backorder' => false, 'stock_quantity' => 100]);
        $this->product([...$attributes, 'sku' => 'SOURCE-WRONG-SUPPLIER', 'supplier_id' => null, 'stock_quantity' => 50]);
        $this->product([...$attributes, 'sku' => 'SOURCE-WATCH', 'name' => 'Unrelated Watch', 'stock_quantity' => 50]);
        $this->product([...$attributes, 'sku' => 'SOURCE-DRAFT', 'is_published' => false, 'stock_quantity' => 50]);
        $parameters = ['q' => 'Clock', 'category' => $zero->category_id, 'supplier' => $supplier->id, 'visibility' => 'published', 'stock_level' => 'low', 'sourcing' => 'enabled'];
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin/inventory?'.http_build_query($parameters))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $low->id)
                ->where('summary.total', 3)->where('summary.in_stock', 1)->where('summary.low_stock', 1)->where('summary.out_stock', 1)
                ->where('summary.units', 5)->where('summary.supplier_units', 8)->where('matching_units', 3)->where('matching_supplier_units', 0));
        $csv = $this->get('/admin/inventory/export?'.http_build_query($parameters))->assertOk()->streamedContent();

        $this->assertStringContainsString('SOURCE-LOW', $csv);
        $this->assertStringNotContainsString('SOURCE-ZERO', $csv);
        $this->assertStringNotContainsString('SOURCE-HIGH', $csv);
        $this->assertStringNotContainsString('SOURCE-DISABLED', $csv);
        $this->assertStringNotContainsString('SOURCE-WRONG-SUPPLIER', $csv);
        $this->assertStringNotContainsString('SOURCE-WATCH', $csv);
        $this->assertStringNotContainsString('SOURCE-DRAFT', $csv);
    }

    public function test_disabled_sourcing_export_contains_disabled_products_even_when_they_have_stock(): void
    {
        $this->product(['sku' => 'DISABLED-ZERO', 'allow_backorder' => false, 'stock_quantity' => 0]);
        $this->product(['sku' => 'DISABLED-STOCKED', 'allow_backorder' => false, 'stock_quantity' => 8]);
        $this->product(['sku' => 'ENABLED-ZERO', 'allow_backorder' => true, 'stock_quantity' => 0]);

        $csv = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/inventory/export?sourcing=disabled')->assertOk()->streamedContent();

        $this->assertStringContainsString('DISABLED-ZERO', $csv);
        $this->assertStringContainsString('DISABLED-STOCKED', $csv);
        $this->assertStringNotContainsString('ENABLED-ZERO', $csv);
    }

    #[DataProvider('paginatedScreens')]
    public function test_sourcing_filter_is_retained_on_every_pagination_page(string $path, int $pageSize): void
    {
        for ($index = 0; $index <= $pageSize; $index++) {
            $this->product(['name' => 'PAGE Clock '.$index, 'allow_backorder' => true]);
        }
        $this->product(['name' => 'PAGE Disabled Clock', 'allow_backorder' => false]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get($path.'?q=PAGE&sourcing=enabled&per_page='.$pageSize)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', $pageSize)->where('products.total', $pageSize + 1)
                ->where('products.next_page_url', fn ($url) => str_contains($url, 'sourcing=enabled') && str_contains($url, 'q=PAGE')));
        $this->get($path.'?q=PAGE&sourcing=enabled&per_page='.$pageSize.'&page=2')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.allow_backorder', true));
    }

    public static function paginatedScreens(): array
    {
        return ['catalog' => ['/admin/products', 15], 'inventory' => ['/admin/inventory', 20]];
    }

    #[DataProvider('invalidSourcingQueries')]
    public function test_invalid_sourcing_filters_return_422_without_changing_products(string $path, string $query, string $message): void
    {
        $product = $this->product(['allow_backorder' => true]);
        $before = $product->refresh()->getRawOriginal();

        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson($path.'?'.$query)->assertUnprocessable()
            ->assertJsonValidationErrors('sourcing')->assertJsonPath('errors.sourcing.0', $message);

        $this->assertSame($before, $product->fresh()->getRawOriginal());
        $this->assertDatabaseCount('inventory_adjustments', 0);
    }

    public static function invalidSourcingQueries(): array
    {
        $cases = [];
        foreach (['catalog' => '/admin/products', 'inventory' => '/admin/inventory', 'export' => '/admin/inventory/export'] as $screen => $path) {
            $cases[$screen.' unsupported value'] = [$path, 'sourcing=unknown', 'The selected sourcing is invalid.'];
            $cases[$screen.' array value'] = [$path, 'sourcing%5B%5D=enabled', 'The sourcing field must be a string.'];
        }

        return $cases;
    }

    #[DataProvider('listScreens')]
    public function test_disabling_selected_sourcing_products_returns_to_filtered_list_without_changing_stock(string $path, string $filterPath): void
    {
        $selected = $this->product(['allow_backorder' => true, 'stock_quantity' => 0]);
        $remaining = $this->product(['allow_backorder' => true, 'stock_quantity' => 3, 'sourced_stock_quantity' => 2]);
        $returnTo = $path.'?sourcing=enabled';
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get($returnTo)->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 2));

        $this->from($returnTo)->post('/admin/products/bulk', ['ids' => [$selected->id], 'bulk_action' => 'disable_sourcing'])
            ->assertRedirect($returnTo)->assertSessionHasNoErrors();
        $this->get($returnTo)->assertOk()->assertInertia(fn (Assert $page) => $page->where($filterPath, 'enabled')
            ->has('products.data', 1)->where('products.data.0.id', $remaining->id));

        $this->assertFalse($selected->refresh()->allow_backorder);
        $this->assertSame(0, $selected->stock_quantity);
        $this->assertSame(0, $selected->sourced_stock_quantity);
        $this->assertSame(3, (int) $remaining->fresh()->stock_quantity);
        $this->assertSame(2, $remaining->sourced_stock_quantity);
        $this->assertDatabaseCount('inventory_adjustments', 0);
    }

    public function test_inventory_keeps_existing_fallbacks_for_unsupported_sort_and_page_size(): void
    {
        $this->product(['allow_backorder' => true]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/inventory?sourcing=enabled&stock_level=unknown&order=unknown&per_page=999')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('sourcing', 'enabled')->where('stock_level', '')
                ->where('order', 'stock_asc')->where('per_page', 20)->has('products.data', 1));
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'sourcing-filter'], ['name' => 'Sourcing Filter']);

        return Product::create(['category_id' => $category->id, 'name' => 'Sourcing Clock', 'regular_price' => 500, 'stock_quantity' => 0, 'source_type' => 'manual', 'is_published' => true, ...$attributes]);
    }

    private function supplier(string $key): DropshipSupplier
    {
        return DropshipSupplier::create(['key' => $key, 'name' => 'Sourcing Supplier', 'driver_key' => 'fake', 'base_url' => 'https://supplier.example']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\InventoryAdjustment;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BulkProductSourcingTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('sourcingActions')]
    public function test_selected_products_change_sourcing_idempotently_without_changing_inventory_or_existing_orders(string $action, bool $enabled): void
    {
        $editor = User::factory()->create(['role' => 'inventory', 'permissions' => ['products.create']]);
        $supplier = DropshipSupplier::create(['key' => 'bulk-sourcing', 'name' => 'Bulk Supplier', 'driver_key' => 'fake', 'base_url' => 'https://supplier.example']);
        $waiting = $this->product(['allow_backorder' => true, 'stock_quantity' => 1, 'sourced_stock_quantity' => 1, 'supplier_id' => $supplier->id, 'source_type' => 'dropshipping']);
        $source = DropshipSupplierProduct::create(['supplier_id' => $supplier->id, 'supplier_product_id' => 'bulk-clock', 'name' => $waiting->name, 'currency' => 'BDT', 'cost_price' => 100, 'stock_qty' => 1, 'fetched_at' => now(), 'raw_payload' => []]);
        $link = DropshipProductLink::create(['supplier_product_row_id' => $source->id, 'product_id' => $waiting->id, 'sync_status' => 'active', 'field_sync_rules' => ['stock' => true]]);
        $order = Order::factory()->create(['status' => 'pending']);
        $item = $order->items()->create(['product_id' => $waiting->id, 'product_name' => $waiting->name, 'quantity' => 5, 'unit_price' => 500, 'line_total' => 2500]);
        app(InventoryStockService::class)->reserveOrderStock($editor, $waiting, $order, $item);
        $waiting->refresh()->update(['allow_backorder' => ! $enabled]);
        $stocked = $this->product(['allow_backorder' => ! $enabled, 'stock_quantity' => 5, 'sourced_stock_quantity' => 2, 'is_published' => false]);
        $unselected = $this->product(['allow_backorder' => ! $enabled, 'stock_quantity' => 4]);
        $before = [$waiting->fresh()->getRawOriginal(), $stocked->getRawOriginal(), $unselected->getRawOriginal()];
        $orderBefore = $order->fresh()->getRawOriginal();
        $itemBefore = $item->fresh()->getRawOriginal();
        $linkBefore = $link->fresh()->getRawOriginal();
        $sourceBefore = $source->fresh()->getRawOriginal();
        $ledgerBefore = InventoryAdjustment::orderBy('id')->get()->map->getRawOriginal()->all();
        Cache::put('sitemap_urls_v2', ['stale']);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->actingAs($editor)->from('/admin/products?q=Clock')->post('/admin/products/bulk', [
                'ids' => [$waiting->id, $stocked->id],
                'bulk_action' => $action,
                'stock_quantity' => 999,
                'sourced_stock_quantity' => 999,
                'is_published' => true,
                'supplier_id' => null,
                'allow_backorder' => ! $enabled,
            ])->assertRedirect('/admin/products?q=Clock')->assertSessionHasNoErrors()
                ->assertSessionHas('status', $enabled
                    ? 'Source-on-order enabled for 2 product(s). Actual inventory is unchanged.'
                    : 'Source-on-order disabled for 2 product(s). Actual inventory is unchanged.');

            $this->assertSame($enabled, $waiting->fresh()->allow_backorder);
            $this->assertSame($enabled, $stocked->fresh()->allow_backorder);
        }

        $this->assertSame(Arr::except($before[0], ['allow_backorder', 'updated_at']), Arr::except($waiting->fresh()->getRawOriginal(), ['allow_backorder', 'updated_at']));
        $this->assertSame(Arr::except($before[1], ['allow_backorder', 'updated_at']), Arr::except($stocked->fresh()->getRawOriginal(), ['allow_backorder', 'updated_at']));
        $this->assertSame($before[2], $unselected->fresh()->getRawOriginal());
        $this->assertSame($orderBefore, $order->fresh()->getRawOriginal());
        $this->assertSame($itemBefore, $item->fresh()->getRawOriginal());
        $this->assertSame(3, $item->fresh()->sourcing_outstanding);
        $this->assertTrue($order->fresh()->awaiting_sourcing);
        $this->assertSame($linkBefore, $link->fresh()->getRawOriginal());
        $this->assertSame($sourceBefore, $source->fresh()->getRawOriginal());
        $this->assertSame($ledgerBefore, InventoryAdjustment::orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertNull(Cache::get('sitemap_urls_v2'));
        $this->assertSame($enabled, $waiting->fresh()->can_order);
        $this->assertSame($enabled, $stocked->fresh()->hide_stock_quantity);
    }

    public static function sourcingActions(): array
    {
        return [
            'enable selected products' => ['enable_sourcing', true],
            'disable selected products' => ['disable_sourcing', false],
        ];
    }

    #[DataProvider('unauthorizedStaff')]
    public function test_sourcing_changes_return_403_for_staff_without_edit_permission(string $action, array $permissions): void
    {
        $product = $this->product(['allow_backorder' => $action === 'disable_sourcing']);
        $before = $product->getRawOriginal();
        $staff = User::factory()->create(['role' => 'inventory', 'permissions' => $permissions]);

        $this->actingAs($staff)->postJson('/admin/products/bulk', ['ids' => [$product->id], 'bulk_action' => $action])->assertForbidden();

        $this->assertSame($before, $product->fresh()->getRawOriginal());
        $this->assertDatabaseEmpty('inventory_adjustments');
    }

    public static function unauthorizedStaff(): array
    {
        return [
            'view only enable' => ['enable_sourcing', ['products.view']],
            'view only disable' => ['disable_sourcing', ['products.view']],
            'delete only enable' => ['enable_sourcing', ['products.delete']],
            'delete only disable' => ['disable_sourcing', ['products.delete']],
        ];
    }

    public function test_unauthenticated_bulk_sourcing_redirects_to_login_without_changes(): void
    {
        $product = $this->product();

        $this->post('/admin/products/bulk', ['ids' => [$product->id], 'bulk_action' => 'enable_sourcing'])->assertRedirectToRoute('admin.login');

        $this->assertFalse($product->fresh()->allow_backorder);
        $this->assertDatabaseEmpty('inventory_adjustments');
    }

    #[DataProvider('invalidBatches')]
    public function test_invalid_batch_returns_422_before_changing_any_selected_product(array $payload, string $error, string $message): void
    {
        $product = $this->product();
        $before = $product->getRawOriginal();
        $editor = User::factory()->create(['role' => 'inventory', 'permissions' => ['products.create']]);
        if (isset($payload['ids']) && is_array($payload['ids'])) {
            $payload['ids'] = array_map(fn ($id) => $id === 'selected' ? $product->id : $id, $payload['ids']);
        }

        $this->actingAs($editor)->postJson('/admin/products/bulk', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors([$error => $message]);

        $this->assertSame($before, $product->fresh()->getRawOriginal());
        $this->assertDatabaseEmpty('inventory_adjustments');
    }

    public static function invalidBatches(): array
    {
        return [
            'missing ids' => [['bulk_action' => 'enable_sourcing'], 'ids', 'The ids field is required.'],
            'empty ids' => [['ids' => [], 'bulk_action' => 'enable_sourcing'], 'ids', 'The ids field is required.'],
            'non-array ids' => [['ids' => 'selected', 'bulk_action' => 'enable_sourcing'], 'ids', 'The ids field must be an array.'],
            'duplicate ids' => [['ids' => ['selected', 'selected'], 'bulk_action' => 'enable_sourcing'], 'ids.0', 'The ids.0 field has a duplicate value.'],
            'nonexistent id alongside valid id' => [['ids' => ['selected', 999999], 'bulk_action' => 'enable_sourcing'], 'ids.1', 'The selected ids.1 is invalid.'],
            'non-integer id alongside valid id' => [['ids' => ['selected', 'bad-id'], 'bulk_action' => 'disable_sourcing'], 'ids.1', 'The ids.1 field must be an integer.'],
            'too many ids' => [['ids' => range(1, 101), 'bulk_action' => 'enable_sourcing'], 'ids', 'The ids field must not have more than 100 items.'],
            'unknown action' => [['ids' => ['selected'], 'bulk_action' => 'enable_sourcings'], 'bulk_action', 'The selected bulk action is invalid.'],
            'missing action' => [['ids' => ['selected']], 'bulk_action', 'The bulk action field is required.'],
            'array action' => [['ids' => ['selected'], 'bulk_action' => ['delete']], 'bulk_action', 'The bulk action field must be a string.'],
        ];
    }

    public function test_delete_only_staff_can_still_delete_a_selected_product(): void
    {
        $selected = $this->product();
        $unselected = $this->product();
        $staff = User::factory()->create(['role' => 'inventory', 'permissions' => ['products.delete']]);

        $this->actingAs($staff)->from('/admin/products')->post('/admin/products/bulk', ['ids' => [$selected->id], 'bulk_action' => 'delete'])
            ->assertRedirect('/admin/products')->assertSessionHas('status', '1 product(s) deleted.');

        $this->assertModelMissing($selected);
        $this->assertModelExists($unselected);
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'bulk-sourcing'], ['name' => 'Bulk Sourcing']);

        return Product::create(array_merge(['name' => 'Clock', 'category_id' => $category->id, 'regular_price' => 500, 'stock_quantity' => 0, 'is_published' => true, 'allow_backorder' => false], $attributes))->refresh();
    }
}

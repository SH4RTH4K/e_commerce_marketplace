<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminFunctionPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_every_protected_admin_route_has_valid_function_permissions(): void
    {
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if (! in_array('admin', $route->gatherMiddleware(), true)) continue;
            $permissions = AdminPermissions::forRoute($route);
            $this->assertNotNull($permissions, $route->uri().' '.$route->getActionName());
            foreach ($permissions as $permission) $this->assertContains($permission, AdminPermissions::keys());
            $checked++;
        }
        $this->assertGreaterThan(200, $checked);
    }

    public function test_no_staff_permission_can_bypass_server_checks_on_any_admin_function(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff', 'permissions' => []]));
        $this->withoutMiddleware(SubstituteBindings::class);
        foreach (Route::getRoutes() as $route) {
            if (! in_array('admin', $route->gatherMiddleware(), true) || AdminPermissions::forRoute($route) === []) continue;
            $uri = '/'.preg_replace('/\{[^}]+\}/', '999', str_replace('{section}', 'guards', $route->uri()));
            $response = $this->json($route->methods()[0], $uri);
            $this->assertSame(403, $response->status(), $route->getActionName().' '.$uri);
        }
    }

    public function test_add_edit_delete_are_independent_for_products_and_bulk_requests(): void
    {
        $category = Category::create(['name' => 'Product Category', 'slug' => 'product-category']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Permission Product', 'slug' => 'permission-product', 'sku' => 'PERM-1', 'regular_price' => 100]);
        $staff = User::factory()->create(['role' => 'staff', 'permissions' => ['products.view', 'products.add']]);
        $this->actingAs($staff)->get('/admin/products/create')->assertOk();
        $this->get('/admin/products/'.$product->id.'/edit')->assertForbidden();
        $this->patch('/admin/products/'.$product->id, [])->assertForbidden();
        $this->delete('/admin/products/'.$product->id)->assertForbidden();
        $staff->update(['permissions' => ['products.edit']]);
        $this->get('/admin/products/'.$product->id.'/edit')->assertOk();
        $this->post('/admin/products', [])->assertForbidden();
        $this->post('/admin/products/bulk', ['ids' => [$product->id], 'bulk_action' => 'delete'])->assertForbidden();
        $image = $product->images()->create(['path' => 'uploads/permission-product.jpg', 'is_primary' => true]);
        $this->delete('/admin/products/'.$product->id.'/images/'.$image->id)->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_bulk_operations_check_the_selected_action(): void
    {
        $category = Category::create(['name' => 'Permission Category', 'slug' => 'permission-category']);
        $this->actingAs(User::factory()->create(['role' => 'staff', 'permissions' => ['categories.edit']]));
        $this->post('/admin/categories/bulk', ['ids' => [$category->id], 'bulk_action' => 'activate'])->assertRedirect();
        $this->post('/admin/categories/bulk', ['ids' => [$category->id], 'bulk_action' => 'delete'])->assertForbidden();
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_execute_permission_allows_moderation_but_not_deletion(): void
    {
        $category = Category::create(['name' => 'Review Category', 'slug' => 'review-category']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Review Product', 'slug' => 'review-product', 'sku' => 'PERM-2', 'regular_price' => 100]);
        $review = ProductReview::create(['product_id' => $product->id, 'rating' => 4, 'author_name' => 'Real Reviewer', 'body' => 'Good quality', 'status' => 'pending']);
        $this->actingAs(User::factory()->create(['role' => 'staff', 'permissions' => ['reviews.execute']]));
        $this->post('/admin/reviews/'.$review->id.'/approve')->assertRedirect();
        $this->assertSame('approved', $review->refresh()->status);
        $this->delete('/admin/reviews/'.$review->id)->assertForbidden();
    }

    public function test_role_management_and_privilege_escalation_are_super_admin_only(): void
    {
        $super = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff', 'username' => 'staff-account', 'permissions' => ['admins.view', 'admins.add', 'admins.edit', 'admins.delete', 'roles.view', 'roles.edit']]);
        $this->actingAs($staff)->get('/admin/roles')->assertForbidden();
        $this->post('/admin/roles', ['key' => 'hijack', 'name' => 'Hijack'])->assertForbidden();
        $this->get('/admin/admins/'.$super->id.'/edit')->assertForbidden();
        $this->put('/admin/admins/'.$staff->id, [
            'name' => $staff->name, 'username' => $staff->username, 'email' => $staff->email,
            'role' => 'admin', 'permissions' => [],
        ])->assertForbidden();
        $this->assertSame('staff', $staff->refresh()->role);
        $this->delete('/admin/admins/'.$super->id)->assertForbidden();
    }

    public function test_legacy_permissions_are_migrated_once_and_can_then_be_revoked_individually(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'permissions' => ['products.view', 'products.create', 'settings.manage']]);
        $migration = require database_path('migrations/2026_10_25_000003_expand_admin_function_permissions.php');
        $migration->up();
        $staff->refresh();
        $this->assertTrue($staff->hasPermission('products.add'));
        $this->assertTrue($staff->hasPermission('products.edit'));
        $this->assertTrue($staff->hasPermission('inventory.view'));
        $this->assertTrue($staff->hasPermission('git_repository.execute'));
        $this->assertNotContains('settings.manage', $staff->permissions);
        $staff->update(['permissions' => ['products.view', 'products.add']]);
        $this->assertFalse($staff->hasPermission('products.edit'));
        $this->assertFalse($staff->hasPermission('inventory.view'));
    }

    public function test_role_changes_update_assigned_users_and_customers_cannot_become_a_staff_role(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $role = Role::create(['key' => 'viewer', 'name' => 'Viewer', 'permissions' => ['categories.view', 'categories.delete']]);
        $user = User::factory()->create(['role' => $role->key, 'permissions' => $role->permissions]);
        $this->put('/admin/roles/'.$role->id, ['name' => 'Viewer', 'permissions' => ['categories.view']])->assertRedirect();
        $this->assertFalse($user->refresh()->hasPermission('categories.delete'));
        $this->post('/admin/roles', ['key' => 'customer', 'name' => 'Customer Staff'])->assertSessionHasErrors('key');
        $this->assertDatabaseMissing('roles', ['key' => 'customer']);
    }

    public function test_supplier_report_export_is_separate_from_view(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'permissions' => ['supplier_reports.view']]);
        $this->actingAs($staff)->get('/admin/suppliers/report')->assertOk();
        $this->get('/admin/suppliers/report?format=csv')->assertForbidden();
        $staff->update(['permissions' => ['supplier_reports.view', 'supplier_reports.execute']]);
        $this->get('/admin/suppliers/report?format=csv')->assertOk();
    }

    public function test_staff_login_uses_an_allowed_page_when_dashboard_is_not_granted(): void
    {
        Role::create(['key' => 'category_viewer', 'name' => 'Category Viewer', 'permissions' => ['categories.view']]);
        $user = User::factory()->create(['username' => 'category-viewer', 'password' => 'test-password', 'role' => 'category_viewer', 'permissions' => null]);
        $this->post('/admin/login', ['username' => 'category-viewer', 'password' => 'test-password'])->assertRedirect('/admin/categories');
        $this->assertAuthenticatedAs($user);
        $this->get('/admin/categories')->assertOk();
        $this->get('/admin')->assertForbidden();
    }
}

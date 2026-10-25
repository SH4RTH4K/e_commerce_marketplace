<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_roles_and_sees_every_permission_group(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin/roles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Roles/Index')
                ->has('roles', 5)
                ->where('availablePermissions.admins.items', fn ($items) => ($items['admins.add'] ?? null) === 'Add')
                ->where('availablePermissions.sync_runs.items', fn ($items) => ($items['sync_runs.execute'] ?? null) === 'Execute'));
    }

    public function test_custom_role_can_be_created_renamed_and_used_by_a_staff_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->post('/admin/roles', [
            'key' => 'warehouse_lead',
            'name' => 'Warehouse Lead',
            'description' => 'Warehouse access',
            'permissions' => ['products.view', 'products.add'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('key', 'warehouse_lead')->firstOrFail();
        $user = User::factory()->create(['role' => $role->key, 'permissions' => null]);

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->hasPermission('products.view'));
        $this->assertFalse($user->hasPermission('orders.view'));

        $this->put('/admin/roles/'.$role->id, [
            'name' => 'Warehouse Supervisor',
            'description' => 'Updated warehouse access',
            'permissions' => ['products.view', 'orders.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $this->assertSame('Warehouse Supervisor', $role->refresh()->name);
        $this->assertSame(['products.view', 'orders.view'], $user->refresh()->permissions);
    }

    public function test_role_delete_explains_dependencies_until_users_are_reassigned(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $role = Role::create([
            'key' => 'temporary_role',
            'name' => 'Temporary Role',
            'permissions' => [],
            'is_system' => false,
        ]);
        $assigned = User::factory()->create(['role' => $role->key]);

        $this->delete('/admin/roles/'.$role->id)
            ->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
        $this->assertStringContainsString($assigned->name, session('errors')->get('role')[0]);

        $assigned->update(['role' => 'manager']);
        $this->delete('/admin/roles/'.$role->id)
            ->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_built_in_roles_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $role = Role::where('key', 'manager')->firstOrFail();

        $this->delete('/admin/roles/'.$role->id)
            ->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }
}

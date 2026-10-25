<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Roles/Index', [
            'roles' => Role::query()->withCount('users')->orderByDesc('is_system')->orderBy('name')->get(),
            'availablePermissions' => User::PERMISSIONS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = Role::create([
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? [],
            'is_system' => false,
        ]);

        return redirect()->route('admin.roles.index')->with('status', "Role '{$role->name}' created successfully.");
    }

    public function update(Request $request, Role $role)
    {
        $data = $this->validated($request, $role);
        $role->name = $data['name'];
        $role->description = $data['description'] ?? null;
        $role->permissions = $role->key === 'admin' ? null : ($data['permissions'] ?? []);
        $role->save();

        if ($role->key !== 'admin') {
            User::query()->where('role', $role->key)->update(['permissions' => json_encode($role->permissions ?? [])]);
        } else {
            User::query()->where('role', $role->key)->update(['permissions' => null]);
        }

        return redirect()->route('admin.roles.index')->with('status', "Role '{$role->name}' updated successfully.");
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->withErrors(['role' => "Built-in role '{$role->name}' is protected. Create a custom role if you need deletable access."]);
        }

        $assigned = $role->users()->select(['id', 'name', 'username'])->orderBy('name')->get();
        if ($assigned->isNotEmpty()) {
            $names = $assigned->take(3)->pluck('name')->implode(', ');
            $more = $assigned->count() > 3 ? ' and '.($assigned->count() - 3).' more' : '';

            return back()->withErrors(['role' => "Cannot delete '{$role->name}'. It is assigned to {$assigned->count()} user(s): {$names}{$more}. Reassign or delete those users first."]);
        }

        $roleName = $role->name;
        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', "Role '{$roleName}' deleted successfully.");
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'key' => [
                $role ? 'nullable' : 'required',
                'string', 'max:60', 'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('roles', 'key')->ignore($role?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Role::permissionKeys())],
        ]);
    }
}

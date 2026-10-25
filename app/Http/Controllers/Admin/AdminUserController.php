<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index()
    {
        $roles = Role::query()->withCount('users')->orderByDesc('is_system')->orderBy('name')->get();
        $admins = User::query()
            ->where(function ($query) use ($roles) {
                $query->whereRaw('LOWER(TRIM(role)) IN ('.implode(',', array_fill(0, count(User::STAFF_ROLES), '?')).')', User::STAFF_ROLES)
                    ->orWhereIn('role', $roles->pluck('key')->all());
            })
            ->with('roleDefinition')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Admins/Index', [
            'admins'               => $admins,
            'roles'                => $roles,
            'availablePermissions' => User::PERMISSIONS,
        ]);
    }

    public function create()
    {
        $roles = Role::query()->orderByDesc('is_system')->orderBy('name')->get();
        $defaultRole = $roles->firstWhere('key', 'manager') ?? $roles->first();

        return Inertia::render('Admin/Admins/Form', [
            'admin'                => new User([
                'role' => $defaultRole?->key ?? 'manager',
                'permissions' => $defaultRole?->permissions ?? [],
            ]),
            'roles'                => $roles,
            'availablePermissions' => User::PERMISSIONS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'username'      => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email'         => ['required', 'email', 'max:180', 'unique:users,email'],
            'password'      => ['required', 'confirmed', Password::defaults()],
            'role'          => ['required', Rule::in(Role::adminKeys())],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(self::knownPermissionKeys())],
        ]);

        User::create([
            'name'              => $data['name'],
            'username'          => $data['username'],
            'email'             => $data['email'],
            'password'          => $data['password'],
            'role'              => $data['role'],
            'permissions'       => $data['role'] === 'admin'
                ? null
                : ($data['permissions'] ?? (Role::findByKey($data['role'])?->permissions ?? [])),
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.admins.index')->with('status', 'User account created successfully.');
    }

    public function edit(User $admin)
    {
        abort_unless($admin->isAdmin(), 404);

        return Inertia::render('Admin/Admins/Form', [
            'admin'                => $admin,
            'roles'                => Role::query()->orderByDesc('is_system')->orderBy('name')->get(),
            'availablePermissions' => User::PERMISSIONS,
        ]);
    }

    public function update(Request $request, User $admin)
    {
        abort_unless($admin->isAdmin(), 404);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'username'      => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($admin->id)],
            'email'         => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($admin->id)],
            'password'      => ['nullable', 'confirmed', Password::defaults()],
            'role'          => ['required', Rule::in(Role::adminKeys())],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(self::knownPermissionKeys())],
        ]);

        if ($admin->isSuperAdmin() && $data['role'] !== 'admin' && User::whereRaw('LOWER(TRIM(role)) = ?', ['admin'])->count() <= 1) {
            return back()->withErrors(['role' => 'At least one Super Admin account must remain.']);
        }

        $admin->name        = $data['name'];
        $admin->username    = $data['username'];
        $admin->email       = $data['email'];
        $admin->role        = $data['role'];
        $admin->permissions = $data['role'] === 'admin'
            ? null
            : ($data['permissions'] ?? (Role::findByKey($data['role'])?->permissions ?? []));

        if (! empty($data['password'])) {
            $admin->password = $data['password'];
        }
        $admin->save();

        return redirect()->route('admin.admins.index')->with('status', 'User account updated successfully.');
    }

    public function destroy(User $admin)
    {
        abort_unless($admin->isAdmin(), 404);

        if ((int) $admin->id === (int) Auth::id()) {
            return back()->withErrors(['admin' => 'You cannot delete your own account while logged in.']);
        }

        if ($admin->isSuperAdmin() && User::whereRaw('LOWER(TRIM(role)) = ?', ['admin'])->count() <= 1) {
            return back()->withErrors(['admin' => 'At least one Super Admin account must remain.']);
        }

        $admin->delete();

        return redirect()->route('admin.admins.index')->with('status', 'User account deleted.');
    }

    public function editPassword()
    {
        return Inertia::render('Admin/Admins/Password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = Auth::user();
        $user->password = $data['password'];
        $user->save();

        return redirect()->route('admin.admins.index')->with('status', 'Your password was updated.');
    }

    private static function knownPermissionKeys(): array
    {
        return Role::permissionKeys();
    }
}

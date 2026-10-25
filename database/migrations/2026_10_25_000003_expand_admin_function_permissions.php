<?php

use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert existing grants once so every new action can subsequently be
        // revoked independently. Keep null user permissions inheriting their role.
        DB::transaction(function () {
            foreach (DB::table('roles')->where('key', '!=', 'admin')->get() as $role) {
                DB::table('roles')->where('id', $role->id)->update([
                    'permissions' => json_encode(AdminPermissions::migrateLegacy(json_decode($role->permissions ?? '[]', true) ?? [])),
                ]);
            }
            DB::table('users')->whereNotNull('permissions')->orderBy('id')->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    if (strtolower(trim((string) $user->role)) === 'admin') continue;
                    DB::table('users')->where('id', $user->id)->update([
                        'permissions' => json_encode(AdminPermissions::migrateLegacy(json_decode($user->permissions, true) ?? [])),
                    ]);
                }
            });
        });
    }

    public function down(): void
    {
        // Retain explicit permissions on rollback: rebuilding broad legacy
        // permissions could grant access that Super Admin deliberately revoked.
    }
};

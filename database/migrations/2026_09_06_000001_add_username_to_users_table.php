<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->nullable()->unique()->after('name');
        });

        $adminId = DB::table('users')
            ->where('email', 'admin@sharthak.com')
            ->value('id') ?? DB::table('users')
                ->where('role', 'admin')
                ->orderBy('id')
                ->value('id');

        if ($adminId !== null) {
            DB::table('users')->where('id', $adminId)->update([
                'username' => 'admin',
                // Pre-hashed production credential; the plaintext is never stored in source.
                'password' => '$2y$12$.yflhn3vghuBscioneRlauuRDCD3EM.fu7MczRt4D2X2JEs0G5WUC',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};

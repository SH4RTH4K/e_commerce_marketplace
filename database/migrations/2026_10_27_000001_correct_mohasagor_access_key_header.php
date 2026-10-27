<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dropship_driver_profiles')) {
            return;
        }

        DB::table('dropship_driver_profiles')
            ->where('key', 'mohasagor')
            ->where('auth_secret_header', 'secret-key')
            ->update([
                'auth_secret_header' => 'access-key',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('dropship_driver_profiles')) {
            return;
        }

        DB::table('dropship_driver_profiles')
            ->where('key', 'mohasagor')
            ->where('auth_secret_header', 'access-key')
            ->update([
                'auth_secret_header' => 'secret-key',
                'updated_at' => now(),
            ]);
    }
};

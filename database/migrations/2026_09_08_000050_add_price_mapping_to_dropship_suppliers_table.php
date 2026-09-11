<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropship_suppliers', function (Blueprint $table) {
            $table->json('price_field_mapping')->nullable()->after('sync_rules');
        });
    }

    public function down(): void
    {
        Schema::table('dropship_suppliers', function (Blueprint $table) {
            $table->dropColumn('price_field_mapping');
        });
    }
};

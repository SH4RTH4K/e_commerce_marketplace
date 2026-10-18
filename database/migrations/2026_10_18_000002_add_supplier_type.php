<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropship_suppliers', function (Blueprint $table) {
            $table->string('supplier_type', 20)->default('dropshipping')->after('name')->index();
            $table->string('base_url')->nullable()->change();
            $table->string('driver_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dropship_suppliers', function (Blueprint $table) {
            $table->dropColumn('supplier_type');
            $table->string('base_url')->nullable(false)->change();
            $table->string('driver_key')->nullable(false)->change();
        });
    }
};

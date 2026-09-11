<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropship_product_links', function (Blueprint $table) {
            $table->json('pricing_snapshot')->nullable()->after('field_sync_rules');
        });
    }

    public function down(): void
    {
        Schema::table('dropship_product_links', function (Blueprint $table) {
            $table->dropColumn('pricing_snapshot');
        });
    }
};

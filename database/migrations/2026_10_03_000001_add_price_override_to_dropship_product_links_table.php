<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropship_product_links', function (Blueprint $table) {
            $table->json('price_override')->nullable()->after('pricing_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('dropship_product_links', function (Blueprint $table) {
            $table->dropColumn('price_override');
        });
    }
};

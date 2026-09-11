<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_flash_sale')->default(false)->after('is_best_seller');
            $table->unsignedInteger('flash_sale_position')->default(0)->after('is_flash_sale');
            $table->index(['is_published', 'is_flash_sale', 'flash_sale_position']);
        });

        // Backfill: products already on sale become the initial flash sale set.
        $pos = 0;
        DB::table('products')
            ->whereNotNull('sale_price')
            ->whereColumn('sale_price', '<', 'regular_price')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id'])
            ->each(function ($row) use (&$pos) {
                DB::table('products')->where('id', $row->id)->update([
                    'is_flash_sale' => true,
                    'flash_sale_position' => $pos++,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_published', 'is_flash_sale', 'flash_sale_position']);
            $table->dropColumn(['is_flash_sale', 'flash_sale_position']);
        });
    }
};

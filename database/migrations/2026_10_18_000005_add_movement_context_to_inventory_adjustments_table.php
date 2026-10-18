<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->string('movement_type', 30)->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('actor_name')->nullable();
            $table->string('stock_scope', 20)->default('local')->index();
        });

        // Retain the existing ledger; older linked-product adjustments describe
        // supplier availability, including overrides made with sync paused.
        DB::table('inventory_adjustments')->where(function ($query) {
            $query->whereExists(function ($query) {
                $query->selectRaw('1')->from('dropship_product_links')->where('product_created_by_integration', true)
                    ->whereColumn('dropship_product_links.product_id', 'inventory_adjustments.product_id');
            })->orWhereExists(function ($query) {
                $query->selectRaw('1')->from('products')->where('source_type', 'dropshipping')
                    ->whereColumn('products.id', 'inventory_adjustments.product_id');
            });
        })->update(['stock_scope' => 'supplier']);
    }

    public function down(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->dropIndex(['stock_scope']);
            $table->dropColumn(['movement_type', 'reference', 'actor_name', 'stock_scope']);
        });
    }
};

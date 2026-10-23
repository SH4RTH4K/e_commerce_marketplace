<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('allow_backorder')->default(false);
            $table->unsignedInteger('sourced_stock_quantity')->default(0);
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->boolean('sourcing_required')->default(false);
            $table->unsignedInteger('reserved_stock_quantity')->nullable();
            $table->unsignedInteger('reserved_sourced_quantity')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['sourcing_required', 'reserved_stock_quantity', 'reserved_sourced_quantity']);
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['allow_backorder', 'sourced_stock_quantity']);
        });
    }
};

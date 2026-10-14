<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->decimal('minimum_price', 12, 2)->nullable();
            $table->decimal('maximum_price', 12, 2)->nullable();
            $table->string('pricing_mode', 16)->default('auto');
            $table->boolean('enforce_minimum_price')->default(false);
            $table->boolean('enforce_maximum_price')->default(false);
            $table->boolean('protect_minimum_price')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['selling_price', 'minimum_price', 'maximum_price', 'pricing_mode', 'enforce_minimum_price', 'enforce_maximum_price', 'protect_minimum_price']);
        });
    }
};

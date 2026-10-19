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
            $table->boolean('sku_managed')->default(false);
            $table->unsignedBigInteger('sku_sequence')->nullable();
        });
        Schema::create('sku_configuration_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name');
            $table->string('action');
            $table->string('scope');
            $table->unsignedInteger('changed_count')->default(0);
            $table->json('config_before')->nullable();
            $table->json('config_after');
            $table->timestamp('created_at');
        });
        Schema::create('sku_change_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('sku_configuration_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('old_sku')->nullable();
            $table->string('new_sku', 60);
        });
        foreach (['sku_configuration' => '{}', 'sku_next_sequence' => '1'] as $key => $value) {
            DB::table('settings')->insertOrIgnore(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_change_items');
        Schema::dropIfExists('sku_configuration_runs');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['sku_managed', 'sku_sequence']));
    }
};

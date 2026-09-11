<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropship_driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 120);
            $table->string('auth_key_header', 100)->default('api-key');
            $table->string('auth_secret_header', 100)->default('secret-key');
            $table->string('products_path', 255)->default('product');
            $table->string('categories_path', 255)->nullable();
            $table->string('collection_path', 255)->nullable();
            $table->string('pagination_param', 80)->default('page');
            $table->char('default_currency', 3)->default('BDT');
            $table->json('field_mapping')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropship_driver_profiles');
    }
};

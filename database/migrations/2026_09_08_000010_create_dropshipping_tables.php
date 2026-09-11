<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropship_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('driver_key');
            $table->string('base_url');
            $table->text('api_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->json('pricing_rules')->nullable();
            $table->json('sync_rules')->nullable();
            $table->unsignedInteger('catalog_cache_seconds')->default(600);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dropship_supplier_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('dropship_suppliers');
            $table->string('supplier_category_key');
            $table->string('parent_category_key')->nullable();
            $table->string('name');
            $table->text('path')->nullable();
            $table->unsignedSmallInteger('level')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_category_key'], 'dsc_supplier_category_unique');
        });

        Schema::create('dropship_category_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('dropship_suppliers');
            $table->string('supplier_category_key');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('mapping_mode', 16)->default('manual');
            $table->boolean('local_category_created_by_integration')->default(false);
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_category_key'], 'dcm_supplier_category_unique');
        });

        Schema::create('dropship_supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('dropship_suppliers');
            $table->string('supplier_product_id');
            $table->string('supplier_category_key')->nullable();
            $table->string('name');
            $table->string('product_code')->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->decimal('cost_price', 14, 2)->nullable();
            $table->decimal('max_price', 14, 2)->nullable();
            $table->decimal('stock_qty', 14, 3)->nullable();
            $table->boolean('is_available')->nullable();
            $table->string('supplier_status')->nullable();
            $table->json('raw_payload');
            $table->char('payload_hash', 64)->nullable();
            $table->timestamp('fetched_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_product_id'], 'dsp_supplier_product_unique');
            $table->index(['supplier_id', 'is_available']);
        });

        Schema::create('dropship_supplier_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_product_row_id')
                ->constrained('dropship_supplier_products')
                ->cascadeOnDelete();
            $table->string('supplier_variant_id');
            $table->string('sku')->nullable();
            $table->json('attributes')->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->decimal('cost_price', 14, 2)->nullable();
            $table->decimal('max_price', 14, 2)->nullable();
            $table->decimal('stock_qty', 14, 3)->nullable();
            $table->boolean('is_available')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_product_row_id', 'supplier_variant_id'], 'dsv_product_variant_unique');
        });

        Schema::create('dropship_product_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_product_row_id')
                ->unique()
                ->constrained('dropship_supplier_products')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('sync_status', 16)->default('active');
            $table->boolean('product_created_by_integration')->default(false);
            $table->json('field_sync_rules')->nullable();
            $table->timestamp('last_data_synced_at')->nullable();
            $table->timestamp('last_price_synced_at')->nullable();
            $table->timestamp('last_stock_synced_at')->nullable();
            $table->timestamp('last_images_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dropship_variant_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_variant_row_id')
                ->unique()
                ->constrained('dropship_supplier_variants')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dropship_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('dropship_suppliers');
            $table->string('type', 32);
            $table->uuid('batch_id')->nullable()->index();
            $table->string('status', 32)->default('queued');
            $table->json('filters')->nullable();
            $table->unsignedInteger('total_items')->nullable();
            $table->unsignedInteger('processed_items')->default(0);
            $table->unsignedInteger('success_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->text('error_summary')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropship_sync_runs');
        Schema::dropIfExists('dropship_variant_links');
        Schema::dropIfExists('dropship_product_links');
        Schema::dropIfExists('dropship_supplier_variants');
        Schema::dropIfExists('dropship_supplier_products');
        Schema::dropIfExists('dropship_category_mappings');
        Schema::dropIfExists('dropship_supplier_categories');
        Schema::dropIfExists('dropship_suppliers');
    }
};

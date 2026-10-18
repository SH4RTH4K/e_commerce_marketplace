<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropship_suppliers', function (Blueprint $table) {
            $table->string('supplier_code', 40)->nullable()->unique()->after('key');
            $table->string('contact_person')->nullable()->after('supplier_type');
            $table->string('mobile', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('business_address')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('division')->nullable();
            $table->string('country')->default('Bangladesh');
            $table->string('business_name')->nullable();
            $table->string('trade_license')->nullable();
            $table->string('tax_vat_bin')->nullable();
            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('source_type', 20)->default('manual')->after('supplier_id')->index();
        });

        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('product_id')->constrained('dropship_suppliers')->nullOnDelete();
            $table->index(['supplier_id', 'created_at']);
        });

        DB::table('dropship_suppliers')->orderBy('id')->get(['id'])->each(function ($supplier): void {
            DB::table('dropship_suppliers')->where('id', $supplier->id)->update([
                'supplier_code' => 'SUP-'.str_pad((string) $supplier->id, 5, '0', STR_PAD_LEFT),
            ]);
        });
        DB::table('products')->whereExists(function ($query) {
            $query->select(DB::raw(1))->from('dropship_product_links')->whereColumn('dropship_product_links.product_id', 'products.id');
        })->update(['source_type' => 'dropshipping']);
    }

    public function down(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('source_type');
        });
        Schema::table('dropship_suppliers', function (Blueprint $table) {
            $table->dropUnique(['supplier_code']);
            $table->dropColumn(['supplier_code', 'contact_person', 'mobile', 'whatsapp', 'email', 'website', 'business_address', 'city', 'district', 'division', 'country', 'business_name', 'trade_license', 'tax_vat_bin', 'payment_terms', 'notes']);
        });
    }
};

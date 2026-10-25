<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();
        DB::table('roles')->insert([
            ['key' => 'admin', 'name' => 'Super Admin', 'description' => 'Full, unrestricted access to every admin feature.', 'permissions' => null, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'manager', 'name' => 'Store Manager', 'description' => 'Products, orders, customers, marketing, media, and store settings.', 'permissions' => json_encode(['products.view', 'products.create', 'products.delete', 'orders.view', 'orders.manage', 'orders.delete', 'customers.manage', 'reviews.manage', 'catalog.manage', 'marketing.manage', 'messages.manage', 'media.manage', 'fraud_guard.manage', 'settings.manage']), 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'support', 'name' => 'Order & Support Agent', 'description' => 'Orders, courier updates, customers, reviews, messages, and product lookup.', 'permissions' => json_encode(['orders.view', 'orders.manage', 'customers.manage', 'reviews.manage', 'messages.manage', 'products.view']), 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'inventory', 'name' => 'Product & Inventory Staff', 'description' => 'Products, stock, categories, and media without order or settings access.', 'permissions' => json_encode(['products.view', 'products.create', 'catalog.manage', 'media.manage']), 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'staff', 'name' => 'Custom Role', 'description' => 'Choose exact permissions manually.', 'permissions' => json_encode([]), 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};

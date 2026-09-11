<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bd_courier_checks', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('source', 30)->default('manual');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('total_parcels')->default(0);
            $table->unsignedInteger('successful_parcels')->default(0);
            $table->unsignedInteger('cancelled_parcels')->default(0);
            $table->decimal('success_ratio', 5, 2)->default(0);
            $table->string('risk_level', 20)->default('unknown');
            $table->json('response');
            $table->unsignedInteger('check_count')->default(1);
            $table->timestamp('last_checked_at');
            $table->timestamps();

            $table->index('last_checked_at');
            $table->index('risk_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bd_courier_checks');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->string('shipping_address');
            $table->string('city');
            $table->string('postal_code')->nullable();
            $table->string('shipping_zone')->default('inside_dhaka'); // inside_dhaka | outside_dhaka

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_charge', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('payment_method')->default('cod'); // cod | bkash | nagad | rocket
            $table->string('payment_sender_number')->nullable();
            $table->string('payment_txn_id')->nullable();
            $table->string('payment_status')->default('pending'); // pending | verified | rejected

            $table->string('status')->default('pending'); // pending|confirmed|processing|shipped|delivered|cancelled
            $table->text('internal_note')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

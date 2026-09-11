<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds a cryptographically random confirmation_token to orders.
     * This token is required to view the order confirmation page,
     * preventing brute-force enumeration of order numbers (SEV-5 fix).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('confirmation_token', 64)
                ->nullable()
                ->unique()
                ->after('order_number')
                ->comment('Random token required to view the confirmation page (anti-enumeration)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('confirmation_token');
        });
    }
};

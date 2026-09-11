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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('courier_provider')->nullable()->after('status');       // steadfast | pathao | redx
            $table->string('courier_tracking_code')->nullable()->after('courier_provider');
            $table->string('courier_consignment_id')->nullable()->after('courier_tracking_code');
            $table->string('courier_status')->nullable()->after('courier_consignment_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['courier_provider', 'courier_tracking_code', 'courier_consignment_id', 'courier_status']);
        });
    }
};

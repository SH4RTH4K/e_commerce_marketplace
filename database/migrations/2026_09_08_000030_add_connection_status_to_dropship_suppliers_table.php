<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropship_suppliers', function (Blueprint $table) {
            $table->string('last_connection_status', 16)->nullable()->after('is_active');
            $table->string('last_connection_message', 500)->nullable()->after('last_connection_status');
            $table->timestamp('last_connection_tested_at')->nullable()->after('last_connection_message');
            $table->timestamp('last_connection_success_at')->nullable()->after('last_connection_tested_at');
            $table->json('capabilities')->nullable()->after('last_connection_success_at');
        });
    }

    public function down(): void
    {
        Schema::table('dropship_suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'last_connection_status',
                'last_connection_message',
                'last_connection_tested_at',
                'last_connection_success_at',
                'capabilities',
            ]);
        });
    }
};

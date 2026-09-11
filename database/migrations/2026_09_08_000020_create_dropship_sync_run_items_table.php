<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropship_sync_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_run_id')->constrained('dropship_sync_runs')->cascadeOnDelete();
            $table->string('item_key');
            $table->string('status', 16)->default('queued');
            $table->text('error_summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['sync_run_id', 'item_key']);
            $table->index(['sync_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropship_sync_run_items');
    }
};

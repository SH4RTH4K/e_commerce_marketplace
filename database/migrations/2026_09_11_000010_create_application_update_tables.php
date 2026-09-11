<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_update_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('provider')->default('github');
            $table->string('repository_type')->default('public');
            $table->string('repository_url')->nullable();
            $table->string('branch')->default('main');
            $table->string('remote_name')->default('origin');
            $table->string('authentication')->default('none');
            $table->text('encrypted_secret')->nullable();
            $table->string('secret_fingerprint', 64)->nullable();
            $table->string('dependency_mode')->default('changed');
            $table->boolean('run_migrations')->default(true);
            $table->boolean('clear_cache')->default(true);
            $table->boolean('health_check')->default(true);
            $table->string('last_checked_commit', 40)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_status')->nullable();
            $table->text('last_message')->nullable();
            $table->timestamps();
        });

        Schema::create('application_deployments', function (Blueprint $table): void {
            $table->id();
            $table->string('repository_url')->nullable();
            $table->string('branch');
            $table->string('previous_commit', 40)->nullable();
            $table->string('target_commit', 40)->nullable();
            $table->string('deployed_commit', 40)->nullable();
            $table->string('status', 30);
            $table->unsignedInteger('commits_applied')->default(0);
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('failed_stage')->nullable();
            $table->string('dependency_status')->nullable();
            $table->string('migration_status')->nullable();
            $table->string('health_status')->nullable();
            $table->text('safe_log')->nullable();
            $table->text('error_summary')->nullable();
            $table->unsignedBigInteger('rollback_of')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_deployments');
        Schema::dropIfExists('application_update_settings');
    }
};

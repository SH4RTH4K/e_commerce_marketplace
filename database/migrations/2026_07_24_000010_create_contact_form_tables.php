<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_form_fields', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label', 120);
            $table->string('type', 30)->default('text'); // text, email, tel, textarea, select, checkbox, file
            $table->string('placeholder', 180)->nullable();
            $table->string('help_text', 255)->nullable();
            $table->json('options')->nullable(); // select options: ["A","B"]
            $table->boolean('is_required')->default(false);
            $table->boolean('is_system')->default(false); // default fields — not deletable
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->string('accept', 120)->nullable(); // file accept attribute
            $table->unsignedInteger('max_size_kb')->nullable(); // file max size
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('new'); // new, read, archived
            $table->string('name', 180)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('subject', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });

        Schema::create('contact_message_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_message_id')->constrained('contact_messages')->cascadeOnDelete();
            $table->string('field_key', 60);
            $table->string('field_label', 120);
            $table->string('field_type', 30)->default('text');
            $table->text('value')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('file_name', 180)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['contact_message_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_message_values');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('contact_form_fields');
    }
};

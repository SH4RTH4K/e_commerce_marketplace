<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->string('text_position', 20)->default('center-left')->change();
            $table->string('image_position', 20)->default('center-center')->change();
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->string('text_position', 10)->default('left')->change();
            $table->string('image_position', 10)->default('center')->change();
        });
    }
};

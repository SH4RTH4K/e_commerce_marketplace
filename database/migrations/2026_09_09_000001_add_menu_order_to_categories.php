<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('menu_order')->default(0)->after('position');
            $table->index(['is_active', 'show_in_menu', 'menu_order']);
        });

        DB::table('categories')->update(['menu_order' => DB::raw('position * 10')]);

        $slugs = [
            'mens-fashion',
            'womens-fashion',
            'kids-zone',
            'gadgets-electronics',
            'watch',
            'home-lifestyle',
            'foods',
            'customize-and-gift',
            'winter',
            'others',
        ];

        foreach ($slugs as $index => $slug) {
            DB::table('categories')->where('slug', $slug)->update([
                'menu_order' => ($index + 1) * 10,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'show_in_menu', 'menu_order']);
            $table->dropColumn('menu_order');
        });
    }
};
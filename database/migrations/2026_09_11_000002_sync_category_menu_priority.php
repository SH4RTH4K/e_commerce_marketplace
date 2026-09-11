<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Before menu_order was introduced, admins edited the visible
        // position field. Preserve that established priority and normalize
        // both columns to the same value.
        DB::table('categories')->update([
            'menu_order' => DB::raw('position'),
        ]);
    }

    public function down(): void
    {
        // The two columns intentionally remain synchronized after this change.
    }
};

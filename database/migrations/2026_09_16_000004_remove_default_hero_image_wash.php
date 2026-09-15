<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The previous default used a 28% white wash, which makes product photos
     * look faded. Preserve intentionally customised values while replacing
     * only that legacy default with the natural, unfiltered image.
     */
    public function up(): void
    {
        Setting::query()
            ->where('key', 'template_1_hero_overlay_opacity')
            ->where('value', '28')
            ->update(['value' => '0']);
    }

    public function down(): void
    {
        Setting::query()
            ->where('key', 'template_1_hero_overlay_opacity')
            ->where('value', '0')
            ->update(['value' => '28']);
    }
};

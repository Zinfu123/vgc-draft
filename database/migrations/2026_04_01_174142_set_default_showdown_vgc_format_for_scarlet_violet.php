<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Default Showdown ladder id for Scarlet & Violet official VGC (update when regulations change).
     * Usage JSON is imported separately via `stats:import-showdown-vgc`.
     */
    public function up(): void
    {
        DB::table('version_groups')
            ->where('slug', 'scarlet-violet')
            ->whereNull('showdown_format_key')
            ->update([
                'showdown_format_key' => 'gen9vgc2026regi',
                'showdown_ladder_rating' => 1760,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('version_groups')
            ->where('slug', 'scarlet-violet')
            ->where('showdown_format_key', 'gen9vgc2026regi')
            ->update([
                'showdown_format_key' => null,
                'showdown_ladder_rating' => null,
                'updated_at' => now(),
            ]);
    }
};

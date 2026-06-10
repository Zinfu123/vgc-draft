<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_pokemon_templates', function (Blueprint $table) {
            $table->foreignId('version_group_id')
                ->nullable()
                ->after('slug')
                ->constrained('version_groups')
                ->nullOnDelete();
        });

        $defaultVersionGroupId = DB::table('version_groups')
            ->orderByDesc('sort_order')
            ->value('id');

        if ($defaultVersionGroupId !== null) {
            DB::table('league_pokemon_templates')
                ->whereNull('version_group_id')
                ->update(['version_group_id' => $defaultVersionGroupId]);
        }
    }

    public function down(): void
    {
        Schema::table('league_pokemon_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('version_group_id');
        });
    }
};

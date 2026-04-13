<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_groups', function (Blueprint $table) {
            $table->string('battle_mechanic')->nullable()->after('showdown_ladder_rating');
        });

        DB::table('version_groups')
            ->whereIn('slug', ['scarlet-violet', 'the-teal-mask', 'the-indigo-disk'])
            ->update(['battle_mechanic' => 'tera', 'updated_at' => now()]);

        DB::table('version_groups')->insert([
            'slug' => 'champions',
            'generation' => 9,
            'sort_order' => 200,
            'name' => 'Pokémon Champions',
            'battle_mechanic' => 'mega',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('version_groups')->where('slug', 'champions')->delete();

        Schema::table('version_groups', function (Blueprint $table) {
            $table->dropColumn('battle_mechanic');
        });
    }
};

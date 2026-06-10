<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('battles');

        Schema::table('set_game_results', function (Blueprint $table) {
            $table->json('p1_knockouts')->nullable()->after('p2_pokemon')->comment('Array of pokedex_ids that caused KOs for p1');
            $table->json('p2_knockouts')->nullable()->after('p1_knockouts')->comment('Array of pokedex_ids that caused KOs for p2');
        });

        Schema::table('pokemon_usage_stats', function (Blueprint $table) {
            $table->unsignedInteger('ko_count')->default(0)->after('game_losses');
        });
    }

    public function down(): void
    {
        Schema::table('pokemon_usage_stats', function (Blueprint $table) {
            $table->dropColumn('ko_count');
        });

        Schema::table('set_game_results', function (Blueprint $table) {
            $table->dropColumn(['p1_knockouts', 'p2_knockouts']);
        });
    }
};

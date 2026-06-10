<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pokemon_usage_stats', function (Blueprint $table) {
            $table->unsignedInteger('game_bring_count')->default(0)->after('match_bring_count');
            $table->decimal('avg_ko_per_game', 8, 4)->nullable()->after('ko_count');
        });
    }

    public function down(): void
    {
        Schema::table('pokemon_usage_stats', function (Blueprint $table) {
            $table->dropColumn(['game_bring_count', 'avg_ko_per_game']);
        });
    }
};

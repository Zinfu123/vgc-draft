<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pokemon_usage_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pokedex_id')->constrained('pokedex')->cascadeOnDelete();
            $table->unsignedInteger('draft_pick_count')->default(0);
            $table->unsignedInteger('draft_ban_count')->default(0);
            $table->unsignedInteger('match_bring_count')->default(0);
            $table->unsignedInteger('game_wins')->default(0);
            $table->unsignedInteger('game_losses')->default(0);
            $table->timestamps();

            $table->unique('pokedex_id');
        });

        Schema::create('pokemon_usage_stats_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('total_picks')->default(0);
            $table->unsignedInteger('total_bans')->default(0);
            $table->unsignedBigInteger('total_bring_units')->default(0);
            $table->timestamp('rebuilt_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pokemon_usage_stats_meta');
        Schema::dropIfExists('pokemon_usage_stats');
    }
};

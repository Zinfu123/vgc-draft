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
        Schema::create('set_game_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('sets')->cascadeOnDelete();
            $table->unsignedTinyInteger('game_number');
            $table->foreignId('p1_team_id')->constrained('teams');
            $table->foreignId('p2_team_id')->constrained('teams');
            $table->foreignId('winner_team_id')->nullable()->constrained('teams');
            $table->json('p1_pokemon')->nullable()->comment('Array of pokedex_ids selected by p1');
            $table->json('p2_pokemon')->nullable()->comment('Array of pokedex_ids selected by p2');
            $table->timestamps();
            $table->unique(['set_id', 'game_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('set_game_results');
    }
};

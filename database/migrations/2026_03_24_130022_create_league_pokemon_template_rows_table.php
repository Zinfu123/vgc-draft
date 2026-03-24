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
        Schema::create('league_pokemon_template_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_pokemon_template_id')->constrained('league_pokemon_templates')->cascadeOnDelete();
            $table->foreignId('pokedex_id')->constrained('pokedex')->cascadeOnDelete();
            $table->unsignedInteger('cost');
            $table->timestamps();

            $table->unique(['league_pokemon_template_id', 'pokedex_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_pokemon_template_rows');
    }
};

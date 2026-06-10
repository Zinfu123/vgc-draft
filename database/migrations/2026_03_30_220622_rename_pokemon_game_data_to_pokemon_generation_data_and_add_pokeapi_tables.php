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
        Schema::create('abilities_generation_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pokedex_id')->constrained('pokedex')->cascadeOnDelete();
            $table->foreignId('version_group_id')->constrained('version_groups')->cascadeOnDelete();
            $table->unsignedInteger('pokeapi_ability_id');
            $table->string('ability_name', 80);
            $table->unsignedTinyInteger('slot');
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->unique(['pokedex_id', 'version_group_id', 'pokeapi_ability_id'], 'abilities_generation_unique');
        });

        Schema::create('pokeapi_move_cache', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name', 80);
            $table->string('type_slug', 32);
            $table->string('damage_class', 16);
            $table->unsignedSmallInteger('power')->nullable();
            $table->unsignedSmallInteger('accuracy')->nullable();
            $table->string('ailment_name', 48)->nullable();
            $table->text('short_effect_en')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::rename('pokemon_game_data', 'pokemon_generation_data');

        Schema::table('pokemon_generation_data', function (Blueprint $table) {
            $table->dropColumn(['ability_primary', 'ability_secondary', 'ability_hidden']);
        });

        Schema::table('pokemon_generation_data', function (Blueprint $table) {
            $table->unsignedInteger('ability_primary_pokeapi_id')->nullable();
            $table->unsignedInteger('ability_secondary_pokeapi_id')->nullable();
            $table->unsignedInteger('ability_hidden_pokeapi_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pokemon_generation_data', function (Blueprint $table) {
            $table->dropColumn([
                'ability_primary_pokeapi_id',
                'ability_secondary_pokeapi_id',
                'ability_hidden_pokeapi_id',
            ]);
        });

        Schema::table('pokemon_generation_data', function (Blueprint $table) {
            $table->string('ability_primary')->nullable();
            $table->string('ability_secondary')->nullable();
            $table->string('ability_hidden')->nullable();
        });

        Schema::rename('pokemon_generation_data', 'pokemon_game_data');

        Schema::dropIfExists('pokeapi_move_cache');
        Schema::dropIfExists('abilities_generation_data');
    }
};

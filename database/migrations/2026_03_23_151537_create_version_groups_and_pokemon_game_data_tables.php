<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('version_groups', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->unsignedTinyInteger('generation');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('leagues', function (Blueprint $table) {
            $table->unsignedTinyInteger('pokemon_generation')->default(9)->after('league_owner');
            $table->string('pokemon_game', 32)->default('scarlet_violet')->after('pokemon_generation');
        });

        Schema::create('pokemon_game_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pokedex_id')->constrained('pokedex')->cascadeOnDelete();
            $table->foreignId('version_group_id')->constrained('version_groups')->cascadeOnDelete();
            $table->unsignedInteger('pokeapi_pokemon_id')->nullable();
            $table->unsignedSmallInteger('hp');
            $table->unsignedSmallInteger('atk');
            $table->unsignedSmallInteger('def');
            $table->unsignedSmallInteger('spa');
            $table->unsignedSmallInteger('spd');
            $table->unsignedSmallInteger('spe');
            $table->string('type1');
            $table->string('type2')->nullable();
            $table->string('ability_primary')->nullable();
            $table->string('ability_secondary')->nullable();
            $table->string('ability_hidden')->nullable();
            $table->json('learnset');
            $table->json('mechanics');
            $table->timestamps();

            $table->unique(['pokedex_id', 'version_group_id']);
        });

        DB::table('version_groups')->insert([
            'slug' => 'scarlet-violet',
            'generation' => 9,
            'sort_order' => 100,
            'name' => 'Scarlet & Violet',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pokemon_game_data');

        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['pokemon_generation', 'pokemon_game']);
        });

        Schema::dropIfExists('version_groups');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pokemon_move_version_data');

        Schema::table('version_groups', function (Blueprint $table) {
            if (Schema::hasColumn('version_groups', 'mechanics_config')) {
                $table->dropColumn('mechanics_config');
            }
        });
    }

    public function down(): void
    {
        Schema::table('version_groups', function (Blueprint $table) {
            $table->json('mechanics_config')->nullable()->after('name');
        });

        Schema::create('pokemon_move_version_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_group_id')->constrained('version_groups')->cascadeOnDelete();
            $table->unsignedInteger('pokeapi_move_id');
            $table->string('name', 80);
            $table->string('type_slug', 32);
            $table->string('damage_class', 16);
            $table->unsignedSmallInteger('power')->nullable();
            $table->unsignedSmallInteger('accuracy')->nullable();
            $table->timestamps();

            $table->unique(['version_group_id', 'pokeapi_move_id'], 'pmvd_version_move_unique');
        });
    }
};

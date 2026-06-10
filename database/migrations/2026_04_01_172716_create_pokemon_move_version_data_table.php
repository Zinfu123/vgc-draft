<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        $vgId = DB::table('version_groups')->where('slug', 'scarlet-violet')->value('id');
        if ($vgId !== null && Schema::hasTable('pokeapi_move_cache')) {
            $nowExpr = Schema::getConnection()->getDriverName() === 'sqlite'
                ? "datetime('now')"
                : 'CURRENT_TIMESTAMP';
            DB::statement("
                INSERT INTO pokemon_move_version_data (
                    version_group_id, pokeapi_move_id, name, type_slug, damage_class, power, accuracy, created_at, updated_at
                )
                SELECT ?, id, name, type_slug, damage_class, power, accuracy, {$nowExpr}, {$nowExpr}
                FROM pokeapi_move_cache
            ", [$vgId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pokemon_move_version_data');
    }
};

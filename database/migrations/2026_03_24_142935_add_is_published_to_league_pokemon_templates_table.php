<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_pokemon_templates', function (Blueprint $table) {
            $table->boolean('is_published')->default(true)->after('version_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('league_pokemon_templates', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });
    }
};

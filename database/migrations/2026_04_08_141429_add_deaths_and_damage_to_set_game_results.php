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
        Schema::table('set_game_results', function (Blueprint $table) {
            $table->json('p1_deaths')->nullable()->after('p2_knockouts');
            $table->json('p2_deaths')->nullable()->after('p1_deaths');
            $table->json('p1_damage')->nullable()->after('p2_deaths');
            $table->json('p2_damage')->nullable()->after('p1_damage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('set_game_results', function (Blueprint $table) {
            $table->dropColumn(['p1_deaths', 'p2_deaths', 'p1_damage', 'p2_damage']);
        });
    }
};

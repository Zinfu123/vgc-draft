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
        Schema::create('draft_bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('no action');
            $table->foreignId('pokedex_id')->constrained('pokedex')->onDelete('cascade');
            $table->integer('round_number')->nullable();
            $table->timestamps();
        });

        Schema::create('draft_ban_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('no action');
            $table->integer('ban_number');
            $table->integer('round_number')->nullable();
            $table->timestamps();
        });

        Schema::table('leagues', function (Blueprint $table) {
            $table->boolean('ban_enabled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_ban_order');
        Schema::dropIfExists('draft_bans');
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('ban_enabled');
        });
    }
};

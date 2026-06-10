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
        Schema::create('playoff_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playoff_id')->constrained('playoffs')->onDelete('cascade');
            $table->string('slot', 64);
            $table->unsignedTinyInteger('round_index');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_bronze')->default(false);
            $table->foreignId('team1_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->foreignId('team2_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->unsignedSmallInteger('team1_score')->nullable();
            $table->unsignedSmallInteger('team2_score')->nullable();
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->json('feeds')->nullable();
            $table->timestamps();

            $table->unique(['playoff_id', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playoff_matches');
    }
};

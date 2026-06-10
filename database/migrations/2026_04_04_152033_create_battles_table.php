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
        Schema::create('battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('sets')->cascadeOnDelete();
            $table->foreignId('p1_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('p2_team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('format')->default('gen9vgc2024regg');
            $table->text('p1_packed_team')->nullable();
            $table->text('p2_packed_team')->nullable();
            $table->enum('status', ['awaiting_teams', 'team_preview', 'active', 'finished'])->default('awaiting_teams');
            $table->string('winner')->nullable(); // 'p1' or 'p2'
            $table->json('battle_log')->default('[]');
            $table->timestamps();

            $table->index(['set_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('battles');
    }
};

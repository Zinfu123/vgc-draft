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
        Schema::create('sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
            $table->foreignId('pool_id')->constrained('pools')->onDelete('cascade');
            $table->integer('round')->default(1);
            $table->foreignId('team1_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('team2_id')->constrained('teams')->onDelete('cascade');
            $table->integer('team1_score')->nullable();
            $table->integer('team2_score')->nullable();
            $table->string('team1_pokepaste')->nullable();
            $table->string('team2_pokepaste')->nullable();
            $table->integer('winner_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sets');
    }
};

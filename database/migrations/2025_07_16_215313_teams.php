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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->integer('pick_position');
            $table->integer('trades')->default(4);
            $table->integer('draft_points')->default(0);
            $table->integer('victory_points')->default(0);
            $table->integer('set_wins')->default(0);
            $table->integer('set_losses')->default(0);
            $table->integer('game_wins')->default(0);
            $table->integer('game_losses')->default(0);
            $table->string('logo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

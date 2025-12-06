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
        Schema::create('draft_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('no action');
            $table->foreignId('user_id')->constrained('users')->onDelete('no action');
            $table->integer('pick_number')->default(1);
            $table->integer('status')->default(1);
            $table->integer('is_last_pick')->default(0);
            $table->string('team_name');
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('no action');
            $table->integer('round_number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_order');
    }
};

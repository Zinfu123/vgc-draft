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
        Schema::create('playoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
            $table->string('format', 32)->default('single_elimination');
            $table->unsignedTinyInteger('bracket_size')->default(4);
            $table->string('status', 24)->default('draft');
            $table->json('seed_order')->nullable();
            $table->timestamps();

            $table->unique('league_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playoffs');
    }
};

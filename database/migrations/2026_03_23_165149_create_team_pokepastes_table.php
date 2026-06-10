<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_pokepastes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained('teams')->cascadeOnDelete();
            $table->json('slots');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_pokepastes');
    }
};

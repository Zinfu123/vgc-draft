<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('team_pokepastes');

        Schema::create('set_team_pokepastes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('sets')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->json('slots');
            $table->timestamps();

            $table->unique(['set_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('set_team_pokepastes');

        Schema::create('team_pokepastes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained('teams')->cascadeOnDelete();
            $table->json('slots');
            $table->timestamps();
        });
    }
};

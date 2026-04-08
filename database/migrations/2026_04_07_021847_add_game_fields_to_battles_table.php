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
        Schema::table('battles', function (Blueprint $table) {
            $table->unsignedTinyInteger('game_number')->default(1)->after('set_id');
            $table->string('winner_side')->nullable()->after('winner'); // 'p1' or 'p2'
            $table->json('p1_pokemon')->nullable()->after('winner_side'); // 4 pokemon brought by p1
            $table->json('p2_pokemon')->nullable()->after('p1_pokemon'); // 4 pokemon brought by p2
        });
    }

    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->dropColumn(['game_number', 'winner_side', 'p1_pokemon', 'p2_pokemon']);
        });
    }
};

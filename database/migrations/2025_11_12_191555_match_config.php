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
        schema::create('match_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
            $table->integer('number_of_pools')->nullable();
            $table->integer('number_of_rounds')->nullable();
            $table->integer('wins_required')->nullable()->default(2);
            $table->boolean('pool_flag')->default(false);
            $table->integer('frequency_type')->nullable();
            $table->integer('duration')->nullable();
            $table->integer('status')->default(1);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        schema::dropIfExists('match_configs');
    }
};

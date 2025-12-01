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
        schema::create('pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_config_id')->constrained('match_configs')->onDelete('cascade');
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
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
        schema::dropIfExists('pools');
    }
};

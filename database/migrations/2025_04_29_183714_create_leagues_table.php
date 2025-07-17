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
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('winner')->constrained('users')->onDelete('cascade')->nullable();
            $table->integer('set_frequency')->default(1);
            $table->string('name');
            $table->string('logo')->nullable();
            $table->date('draft_date')->nullable();
            $table->date('set_start_date')->nullable();
            $table->integer('status')->default(1);
            $table->integer('draft_points')->default(80); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};

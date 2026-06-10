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
        Schema::create('version_group_held_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_group_id')->constrained('version_groups')->cascadeOnDelete();
            $table->unsignedInteger('pokeapi_item_id');
            $table->string('name');
            $table->string('display_name_en')->nullable();
            $table->unsignedInteger('cost')->nullable();
            $table->string('sprite_url')->nullable();
            $table->timestamps();

            $table->unique(['version_group_id', 'pokeapi_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('version_group_held_items');
    }
};

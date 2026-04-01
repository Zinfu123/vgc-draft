<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vgc_ladder_species_usage', function (Blueprint $table) {
            $table->id();
            $table->string('format_key', 64);
            $table->string('period', 8);
            $table->string('species_key', 80);
            $table->decimal('usage_percent', 8, 4)->default(0);
            $table->json('detail')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['format_key', 'period', 'species_key'], 'vgc_usage_unique');
            $table->index(['format_key', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vgc_ladder_species_usage');
    }
};

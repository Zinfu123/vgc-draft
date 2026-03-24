<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_prep_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('set_id')->constrained('sets')->cascadeOnDelete();
            $table->json('plan_1_slots');
            $table->json('plan_2_slots');
            $table->json('plan_3_slots');
            $table->text('plan_1_notes')->nullable();
            $table->text('plan_2_notes')->nullable();
            $table->text('plan_3_notes')->nullable();
            $table->text('replay_notes')->nullable();
            $table->json('calcs')->nullable();
            $table->boolean('share_enabled')->default(false);
            $table->uuid('share_uuid')->nullable()->unique();
            $table->timestamps();

            $table->unique(['user_id', 'set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_prep_notes');
    }
};

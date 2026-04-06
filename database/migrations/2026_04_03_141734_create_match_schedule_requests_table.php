<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_schedule_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('sets')->cascadeOnDelete();
            $table->foreignId('proposed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('proposed_at');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['set_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_schedule_requests');
    }
};

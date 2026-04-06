<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('sets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['set_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_messages');
    }
};

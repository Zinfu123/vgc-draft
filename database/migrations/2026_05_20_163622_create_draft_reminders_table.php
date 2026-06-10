<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_id')->constrained('drafts')->cascadeOnDelete();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->unsignedInteger('threshold_seconds');
            $table->timestamp('fire_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['fire_at', 'sent_at', 'cancelled_at'], 'draft_reminders_due_idx');
            $table->index(['draft_id', 'sent_at', 'cancelled_at'], 'draft_reminders_draft_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_reminders');
    }
};

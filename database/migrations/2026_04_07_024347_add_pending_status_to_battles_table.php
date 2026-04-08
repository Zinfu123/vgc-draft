<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert status from a restricted enum to a plain varchar so we can
     * add 'pending' without database-specific ALTER TYPE gymnastics.
     */
    public function up(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->enum('status', ['awaiting_teams', 'team_preview', 'active', 'finished'])
                ->default('awaiting_teams')
                ->change();
        });
    }
};

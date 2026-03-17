<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->unique()->constrained('leagues')->onDelete('cascade');
            $table->date('draft_date')->nullable();
            $table->integer('draft_points')->default(80);
            $table->integer('minimum_drafts')->default(0);
            $table->boolean('enforce_round_count')->default(false);
            $table->integer('round_count')->nullable();
            $table->boolean('ban_enabled')->default(false);
            $table->timestamps();
        });

        DB::statement("
            INSERT INTO draft_config (league_id, draft_date, draft_points, minimum_drafts, enforce_round_count, round_count, ban_enabled, created_at, updated_at)
            SELECT id, draft_date, draft_points, minimum_drafts, enforce_round_count, round_count, ban_enabled, datetime('now'), datetime('now')
            FROM leagues
        ");

        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn([
                'draft_date',
                'draft_points',
                'minimum_drafts',
                'enforce_round_count',
                'round_count',
                'ban_enabled',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->date('draft_date')->nullable();
            $table->integer('draft_points')->default(80);
            $table->integer('minimum_drafts')->default(0);
            $table->boolean('enforce_round_count')->default(false);
            $table->integer('round_count')->nullable();
            $table->boolean('ban_enabled')->default(false);
        });

        DB::statement('
            UPDATE leagues l
            JOIN draft_config dc ON dc.league_id = l.id
            SET l.draft_date = dc.draft_date,
                l.draft_points = dc.draft_points,
                l.minimum_drafts = dc.minimum_drafts,
                l.enforce_round_count = dc.enforce_round_count,
                l.round_count = dc.round_count,
                l.ban_enabled = dc.ban_enabled
        ');

        Schema::dropIfExists('draft_config');
    }
};

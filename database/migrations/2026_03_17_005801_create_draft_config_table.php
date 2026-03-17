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

        $hasBanEnabled = Schema::hasColumn('leagues', 'ban_enabled');

        DB::table('leagues')->get()->each(function ($league) use ($hasBanEnabled) {
            DB::table('draft_config')->insert([
                'league_id' => $league->id,
                'draft_date' => $league->draft_date,
                'draft_points' => $league->draft_points,
                'minimum_drafts' => $league->minimum_drafts,
                'enforce_round_count' => $league->enforce_round_count,
                'round_count' => $league->round_count,
                'ban_enabled' => $hasBanEnabled ? $league->ban_enabled : false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $columnsToDrop = ['draft_date', 'draft_points', 'minimum_drafts', 'enforce_round_count', 'round_count'];

        if ($hasBanEnabled) {
            $columnsToDrop[] = 'ban_enabled';
        }

        Schema::table('leagues', function (Blueprint $table) use ($columnsToDrop) {
            $table->dropColumn($columnsToDrop);
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

        DB::table('draft_config')->get()->each(function ($config) {
            DB::table('leagues')->where('id', $config->league_id)->update([
                'draft_date' => $config->draft_date,
                'draft_points' => $config->draft_points,
                'minimum_drafts' => $config->minimum_drafts,
                'enforce_round_count' => $config->enforce_round_count,
                'round_count' => $config->round_count,
                'ban_enabled' => $config->ban_enabled,
            ]);
        });

        Schema::dropIfExists('draft_config');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_configs', function (Blueprint $table) {
            $table->boolean('enforce_round_count')->default(false)->after('frequency_value');
            $table->integer('round_count')->nullable()->after('enforce_round_count');
        });

        DB::statement('
            UPDATE match_configs
            SET enforce_round_count = (
                SELECT enforce_round_count FROM draft_config WHERE draft_config.league_id = match_configs.league_id
            ),
            round_count = (
                SELECT round_count FROM draft_config WHERE draft_config.league_id = match_configs.league_id
            )
        ');

        Schema::table('draft_config', function (Blueprint $table) {
            $table->dropColumn(['enforce_round_count', 'round_count']);
        });
    }

    public function down(): void
    {
        Schema::table('draft_config', function (Blueprint $table) {
            $table->boolean('enforce_round_count')->default(false);
            $table->integer('round_count')->nullable();
        });

        DB::statement('
            UPDATE draft_config
            SET enforce_round_count = (
                SELECT enforce_round_count FROM match_configs WHERE match_configs.league_id = draft_config.league_id
            ),
            round_count = (
                SELECT round_count FROM match_configs WHERE match_configs.league_id = draft_config.league_id
            )
        ');

        Schema::table('match_configs', function (Blueprint $table) {
            $table->dropColumn(['enforce_round_count', 'round_count']);
        });
    }
};

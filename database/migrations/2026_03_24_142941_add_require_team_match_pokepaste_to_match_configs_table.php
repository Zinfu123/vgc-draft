<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_configs', function (Blueprint $table) {
            $table->boolean('require_team_match_pokepaste_before_results')->default(false)->after('round_count');
        });
    }

    public function down(): void
    {
        Schema::table('match_configs', function (Blueprint $table) {
            $table->dropColumn('require_team_match_pokepaste_before_results');
        });
    }
};

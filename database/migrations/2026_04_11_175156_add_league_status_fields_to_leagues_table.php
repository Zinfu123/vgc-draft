<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remap existing data before changing the default:
        // Old status 0 = completed → new status 1 (Completed)
        // Old status 1 = active   → new status 4 (RegularSeason)
        DB::statement('UPDATE leagues SET status = 1 WHERE status = 0');
        DB::statement('UPDATE leagues SET status = 4 WHERE status = 1');

        Schema::table('leagues', function (Blueprint $table) {
            $table->integer('status')->default(2)->change();
            $table->integer('staging_sub_status')->nullable()->after('status');
            $table->integer('free_trade_window_hours')->default(24)->after('staging_sub_status');
            $table->boolean('playoffs_enabled')->default(true)->after('free_trade_window_hours');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['staging_sub_status', 'free_trade_window_hours', 'playoffs_enabled']);
            $table->integer('status')->default(1)->change();
        });

        // Remap back: status 1 (Completed) → 0, status 4 (RegularSeason) → 1
        DB::statement('UPDATE leagues SET status = 0 WHERE status = 1');
        DB::statement('UPDATE leagues SET status = 1 WHERE status = 4');
    }
};

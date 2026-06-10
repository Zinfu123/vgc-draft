<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_config', function (Blueprint $table) {
            $table->boolean('pick_timer_enabled')->default(false)->after('minimum_cost_to_ban');
            $table->unsignedInteger('pick_timer_seconds')->nullable()->after('pick_timer_enabled');
            $table->boolean('quiet_hours_enabled')->default(false)->after('pick_timer_seconds');
            $table->time('quiet_hours_start')->nullable()->after('quiet_hours_enabled');
            $table->time('quiet_hours_end')->nullable()->after('quiet_hours_start');
            $table->string('quiet_hours_timezone')->nullable()->after('quiet_hours_end');
        });

        Schema::table('drafts', function (Blueprint $table) {
            $table->timestamp('current_deadline_at')->nullable()->after('pick_number');
            $table->timestamp('paused_at')->nullable()->after('current_deadline_at');
            $table->unsignedInteger('paused_remaining_seconds')->nullable()->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('drafts', function (Blueprint $table) {
            $table->dropColumn(['current_deadline_at', 'paused_at', 'paused_remaining_seconds']);
        });

        Schema::table('draft_config', function (Blueprint $table) {
            $table->dropColumn([
                'pick_timer_enabled',
                'pick_timer_seconds',
                'quiet_hours_enabled',
                'quiet_hours_start',
                'quiet_hours_end',
                'quiet_hours_timezone',
            ]);
        });
    }
};

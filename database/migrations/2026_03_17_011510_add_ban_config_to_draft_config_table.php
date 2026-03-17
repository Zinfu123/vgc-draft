<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_config', function (Blueprint $table) {
            $table->integer('bans_per_user')->default(1)->after('ban_enabled');
            $table->integer('minimum_cost_to_ban')->default(0)->after('bans_per_user');
        });
    }

    public function down(): void
    {
        Schema::table('draft_config', function (Blueprint $table) {
            $table->dropColumn(['bans_per_user', 'minimum_cost_to_ban']);
        });
    }
};

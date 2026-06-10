<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->string('discord_replay_webhook_url')->nullable()->after('discord_webhook_url');
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->string('replay1')->nullable()->after('team2_pokepaste');
            $table->string('replay2')->nullable()->after('replay1');
            $table->string('replay3')->nullable()->after('replay2');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('discord_replay_webhook_url');
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->dropColumn(['replay1', 'replay2', 'replay3']);
        });
    }
};

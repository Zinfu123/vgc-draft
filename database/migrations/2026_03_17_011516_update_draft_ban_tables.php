<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_bans', function (Blueprint $table) {
            $table->foreignId('pokedex_id')->nullable()->change();
            $table->integer('status')->default(0)->after('round_number');
        });

        Schema::table('draft_ban_order', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('no action')->after('team_id');
            $table->string('team_name')->nullable()->after('user_id');
            $table->integer('status')->default(1)->after('round_number');
            $table->integer('is_last_ban')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('draft_bans', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->foreignId('pokedex_id')->nullable(false)->change();
        });

        Schema::table('draft_ban_order', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'team_name', 'status', 'is_last_ban']);
        });
    }
};

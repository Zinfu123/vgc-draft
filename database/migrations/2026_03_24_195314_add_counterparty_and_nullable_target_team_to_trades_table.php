<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('trades', 'counterparty')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            $table->dropForeign(['target_team_id']);
        });

        Schema::table('trades', function (Blueprint $table) {
            $table->foreignId('target_team_id')->nullable()->change();
            $table->string('counterparty', 32)->default('team')->after('target_team_id');
        });

        Schema::table('trades', function (Blueprint $table) {
            $table->foreign('target_team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropForeign(['target_team_id']);
        });

        Schema::table('trades', function (Blueprint $table) {
            $table->dropColumn('counterparty');
            $table->foreignId('target_team_id')->nullable(false)->change();
        });

        Schema::table('trades', function (Blueprint $table) {
            $table->foreign('target_team_id')->references('id')->on('teams')->cascadeOnDelete();
        });
    }
};

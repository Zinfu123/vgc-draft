<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sets', function (Blueprint $table) {
            $table->dropForeign(['team1_id']);
            $table->dropForeign(['team2_id']);
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->foreignId('team1_id')->nullable()->change();
            $table->foreignId('team2_id')->nullable()->change();
            $table->boolean('is_bye')->default(false)->after('status');
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->foreign('team1_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('team2_id')->references('id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sets', function (Blueprint $table) {
            $table->dropForeign(['team1_id']);
            $table->dropForeign(['team2_id']);
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->dropColumn('is_bye');
            $table->foreignId('team1_id')->nullable(false)->change();
            $table->foreignId('team2_id')->nullable(false)->change();
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->foreign('team1_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('team2_id')->references('id')->on('teams')->cascadeOnDelete();
        });
    }
};

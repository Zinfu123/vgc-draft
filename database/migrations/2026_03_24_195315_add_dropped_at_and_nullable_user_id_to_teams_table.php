<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->timestamp('dropped_at')->nullable()->after('updated_at');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('dropped_at');
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};

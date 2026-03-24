<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_config', function (Blueprint $table) {
            $table->integer('bans_per_user')->nullable()->default(null)->change();
            $table->integer('minimum_cost_to_ban')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('draft_config', function (Blueprint $table) {
            $table->integer('bans_per_user')->nullable(false)->default(1)->change();
            $table->integer('minimum_cost_to_ban')->nullable(false)->default(0)->change();
        });
    }
};

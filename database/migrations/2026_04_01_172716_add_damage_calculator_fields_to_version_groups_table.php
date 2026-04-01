<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_groups', function (Blueprint $table) {
            $table->string('showdown_format_key', 64)->nullable()->after('name');
            $table->unsignedSmallInteger('showdown_ladder_rating')->nullable()->after('showdown_format_key');
        });
    }

    public function down(): void
    {
        Schema::table('version_groups', function (Blueprint $table) {
            $table->dropColumn([
                'showdown_format_key',
                'showdown_ladder_rating',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->boolean('details_visible')->default(false)->after('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->dropColumn('details_visible');
        });
    }
};

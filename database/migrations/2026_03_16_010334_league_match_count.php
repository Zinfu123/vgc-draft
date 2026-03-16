<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
        Schema::table('leagues', function (Blueprint $table) {
            $table->boolean('enforce_match_count')->default(false)->after('draft_points');
            $table->integer('match_count')->nullable()->after('enforce_match_count')->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('enforce_match_count');
            $table->dropColumn('match_count');
        });
    }
};

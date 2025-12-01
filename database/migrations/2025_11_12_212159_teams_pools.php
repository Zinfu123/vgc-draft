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
        schema::table('teams', function (Blueprint $table) {
            $table->foreignId('pool_id')->nullable()->constrained('pools');
            $table->integer('seed')->incrementing()->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['pool_id']);
            $table->dropColumn('pool_id');
            $table->dropColumn('seed');
        });
    }
};

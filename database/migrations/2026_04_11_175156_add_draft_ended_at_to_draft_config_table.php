<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_config', function (Blueprint $table) {
            $table->timestamp('draft_ended_at')->nullable()->after('draft_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('draft_config', function (Blueprint $table) {
            $table->dropColumn('draft_ended_at');
        });
    }
};

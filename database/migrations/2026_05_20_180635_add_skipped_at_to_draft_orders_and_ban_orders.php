<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_order', function (Blueprint $table) {
            $table->timestamp('skipped_at')->nullable()->after('status');
        });

        Schema::table('draft_ban_order', function (Blueprint $table) {
            $table->timestamp('skipped_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('draft_order', function (Blueprint $table) {
            $table->dropColumn('skipped_at');
        });

        Schema::table('draft_ban_order', function (Blueprint $table) {
            $table->dropColumn('skipped_at');
        });
    }
};

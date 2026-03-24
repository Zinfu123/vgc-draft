<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_prep_notes', function (Blueprint $table) {
            $table->dropColumn('replay_notes');
        });
    }

    public function down(): void
    {
        Schema::table('match_prep_notes', function (Blueprint $table) {
            $table->text('replay_notes')->nullable()->after('plan_3_notes');
        });
    }
};

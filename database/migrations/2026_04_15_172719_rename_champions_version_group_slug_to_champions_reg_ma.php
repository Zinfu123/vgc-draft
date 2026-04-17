<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('version_groups')
            ->where('slug', 'champions')
            ->update(['slug' => 'champions-reg-ma', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('version_groups')
            ->where('slug', 'champions-reg-ma')
            ->update(['slug' => 'champions', 'updated_at' => now()]);
    }
};

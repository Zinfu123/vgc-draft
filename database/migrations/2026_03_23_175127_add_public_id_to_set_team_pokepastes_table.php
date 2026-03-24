<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->unsignedBigInteger('public_id')->nullable()->unique()->after('id');
        });

        foreach (DB::table('set_team_pokepastes')->whereNull('public_id')->cursor() as $row) {
            DB::table('set_team_pokepastes')->where('id', $row->id)->update([
                'public_id' => $this->generateUniquePublicId(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }

    private function generateUniquePublicId(): int
    {
        do {
            $id = random_int(1_000_000_000_000_000, 9_223_372_036_854_775_807);
        } while (DB::table('set_team_pokepastes')->where('public_id', $id)->exists());

        return $id;
    }
};

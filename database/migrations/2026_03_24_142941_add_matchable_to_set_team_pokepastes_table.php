<?php

use App\Modules\Matches\Models\Set;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->string('matchable_type')->nullable()->after('id');
            $table->unsignedBigInteger('matchable_id')->nullable()->after('matchable_type');
        });

        $setClass = Set::class;
        DB::table('set_team_pokepastes')->orderBy('id')->each(function (object $row) use ($setClass): void {
            DB::table('set_team_pokepastes')->where('id', $row->id)->update([
                'matchable_type' => $setClass,
                'matchable_id' => $row->set_id,
            ]);
        });

        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->dropForeign(['set_id']);
        });

        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->dropUnique(['set_id', 'team_id']);
            $table->dropColumn('set_id');
        });

        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->string('matchable_type')->nullable(false)->change();
            $table->unsignedBigInteger('matchable_id')->nullable(false)->change();
            $table->unique(['matchable_type', 'matchable_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->dropUnique(['matchable_type', 'matchable_id', 'team_id']);
        });

        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->foreignId('set_id')->nullable()->after('team_id')->constrained('sets')->cascadeOnDelete();
        });

        $setClass = Set::class;
        DB::table('set_team_pokepastes')->orderBy('id')->each(function (object $row) use ($setClass): void {
            if ($row->matchable_type === $setClass) {
                DB::table('set_team_pokepastes')->where('id', $row->id)->update([
                    'set_id' => $row->matchable_id,
                ]);
            }
        });

        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->unsignedBigInteger('set_id')->nullable(false)->change();
            $table->unique(['set_id', 'team_id']);
            $table->dropColumn(['matchable_type', 'matchable_id']);
        });
    }
};

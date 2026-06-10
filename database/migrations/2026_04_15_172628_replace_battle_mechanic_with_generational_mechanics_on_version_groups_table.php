<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_groups', function (Blueprint $table) {
            $table->json('generational_mechanics')->nullable()->after('battle_mechanic');
        });

        DB::table('version_groups')->get()->each(function (object $row): void {
            $mechanic = match ($row->battle_mechanic) {
                'tera' => [1],
                'mega' => [2],
                default => [0],
            };

            DB::table('version_groups')
                ->where('id', $row->id)
                ->update(['generational_mechanics' => json_encode($mechanic)]);
        });

        Schema::table('version_groups', function (Blueprint $table) {
            $table->dropColumn('battle_mechanic');
        });
    }

    public function down(): void
    {
        Schema::table('version_groups', function (Blueprint $table) {
            $table->string('battle_mechanic')->nullable()->after('generational_mechanics');
        });

        DB::table('version_groups')->get()->each(function (object $row): void {
            $mechanics = json_decode((string) $row->generational_mechanics, true) ?? [];
            $battleMechanic = null;
            if (in_array(1, $mechanics, true)) {
                $battleMechanic = 'tera';
            } elseif (in_array(2, $mechanics, true)) {
                $battleMechanic = 'mega';
            }

            DB::table('version_groups')
                ->where('id', $row->id)
                ->update(['battle_mechanic' => $battleMechanic]);
        });

        Schema::table('version_groups', function (Blueprint $table) {
            $table->dropColumn('generational_mechanics');
        });
    }
};

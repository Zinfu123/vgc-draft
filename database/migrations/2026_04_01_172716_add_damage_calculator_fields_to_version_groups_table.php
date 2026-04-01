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
            $table->json('mechanics_config')->nullable()->after('name');
            $table->string('showdown_format_key', 64)->nullable()->after('mechanics_config');
            $table->unsignedSmallInteger('showdown_ladder_rating')->nullable()->after('showdown_format_key');
        });

        $defaultMechanics = [
            'formula' => 'gen9',
            'type_chart' => 'gen6_fairy',
            'damage_roll_min' => 0.85,
            'damage_roll_max' => 1.0,
            'tera_enabled' => true,
            'default_battle' => 'doubles',
        ];

        DB::table('version_groups')->where('slug', 'scarlet-violet')->update([
            'mechanics_config' => json_encode($defaultMechanics),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('version_groups', function (Blueprint $table) {
            $table->dropColumn([
                'mechanics_config',
                'showdown_format_key',
                'showdown_ladder_rating',
            ]);
        });
    }
};

<?php

use App\Modules\Matches\Models\Pool;
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
        Schema::table('pools', function (Blueprint $table) {
            $table->string('name')->nullable()->after('league_id');
        });

        Pool::query()
            ->orderBy('league_id')
            ->orderBy('id')
            ->get()
            ->groupBy('league_id')
            ->each(function ($pools): void {
                $pools->values()->each(function (Pool $pool, int $index): void {
                    $pool->forceFill(['name' => 'Pool '.($index + 1)])->save();
                });
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};

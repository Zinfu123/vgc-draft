<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('draft_wishlist_items', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('league_pokemon_id');
        });

        $rows = DB::table('draft_wishlist_items')->orderBy('team_id')->orderBy('id')->get(['id', 'team_id']);
        $byTeam = [];
        foreach ($rows as $row) {
            $byTeam[(int) $row->team_id][] = (int) $row->id;
        }
        foreach ($byTeam as $ids) {
            foreach ($ids as $order => $id) {
                DB::table('draft_wishlist_items')->where('id', $id)->update(['sort_order' => $order]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('draft_wishlist_items', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};

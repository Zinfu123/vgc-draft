<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('set_team_pokepaste_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_team_pokepaste_id')->constrained('set_team_pokepastes')->cascadeOnDelete();
            $table->unsignedTinyInteger('slot_index');
            $table->foreignId('league_pokemon_id')->nullable()->constrained('league_pokemon')->nullOnDelete();
            $table->string('ability', 120)->nullable();
            $table->json('moves')->nullable();
            $table->foreignId('version_group_held_item_id')->nullable()->constrained('version_group_held_items')->nullOnDelete();
            $table->unsignedTinyInteger('nature')->nullable();
            $table->string('tera_type', 40)->nullable();
            $table->unsignedSmallInteger('ev_hp')->default(0);
            $table->unsignedSmallInteger('ev_atk')->default(0);
            $table->unsignedSmallInteger('ev_def')->default(0);
            $table->unsignedSmallInteger('ev_spa')->default(0);
            $table->unsignedSmallInteger('ev_spd')->default(0);
            $table->unsignedSmallInteger('ev_spe')->default(0);
            $table->timestamps();

            $table->unique(['set_team_pokepaste_id', 'slot_index']);
        });

        foreach (DB::table('set_team_pokepastes')->cursor() as $paste) {
            $decoded = json_decode($paste->slots ?? '[]', true);
            $slots = is_array($decoded) ? $decoded : [];

            for ($i = 0; $i < 6; $i++) {
                $s = $slots[$i] ?? [];
                if (! is_array($s)) {
                    $s = [];
                }

                $moves = $s['moves'] ?? ['', '', '', ''];
                if (! is_array($moves)) {
                    $moves = ['', '', '', ''];
                }
                $moves = array_values(array_pad(array_slice($moves, 0, 4), 4, ''));

                $ev = is_array($s['evs'] ?? null) ? $s['evs'] : [];

                DB::table('set_team_pokepaste_slots')->insert([
                    'set_team_pokepaste_id' => $paste->id,
                    'slot_index' => $i,
                    'league_pokemon_id' => isset($s['league_pokemon_id']) && (int) $s['league_pokemon_id'] > 0
                        ? (int) $s['league_pokemon_id']
                        : null,
                    'ability' => isset($s['ability']) && trim((string) $s['ability']) !== '' ? trim((string) $s['ability']) : null,
                    'moves' => json_encode($moves),
                    'version_group_held_item_id' => isset($s['version_group_held_item_id']) && (int) $s['version_group_held_item_id'] > 0
                        ? (int) $s['version_group_held_item_id']
                        : null,
                    'nature' => isset($s['nature']) && $s['nature'] !== '' && $s['nature'] !== null
                        ? (int) $s['nature']
                        : null,
                    'tera_type' => isset($s['tera_type']) && $s['tera_type'] !== '' ? trim((string) $s['tera_type']) : null,
                    'ev_hp' => max(0, min(252, (int) ($ev['hp'] ?? 0))),
                    'ev_atk' => max(0, min(252, (int) ($ev['atk'] ?? 0))),
                    'ev_def' => max(0, min(252, (int) ($ev['def'] ?? 0))),
                    'ev_spa' => max(0, min(252, (int) ($ev['spa'] ?? 0))),
                    'ev_spd' => max(0, min(252, (int) ($ev['spd'] ?? 0))),
                    'ev_spe' => max(0, min(252, (int) ($ev['spe'] ?? 0))),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->dropColumn('slots');
        });
    }

    public function down(): void
    {
        Schema::table('set_team_pokepastes', function (Blueprint $table) {
            $table->json('slots')->nullable()->after('team_id');
        });

        foreach (DB::table('set_team_pokepastes')->cursor() as $paste) {
            $rows = DB::table('set_team_pokepaste_slots')
                ->where('set_team_pokepaste_id', $paste->id)
                ->orderBy('slot_index')
                ->get()
                ->keyBy(fn ($r) => (int) $r->slot_index);

            $empty = [
                'league_pokemon_id' => null,
                'ability' => '',
                'moves' => ['', '', '', ''],
                'version_group_held_item_id' => null,
                'nature' => null,
                'tera_type' => null,
                'evs' => null,
            ];

            $ordered = [];
            for ($i = 0; $i < 6; $i++) {
                $row = $rows->get($i);
                if ($row === null) {
                    $ordered[] = $empty;

                    continue;
                }

                $moves = json_decode($row->moves ?? '[]', true);
                if (! is_array($moves)) {
                    $moves = ['', '', '', ''];
                }
                $moves = array_values(array_pad(array_slice($moves, 0, 4), 4, ''));

                $evs = [];
                foreach (['hp' => 'ev_hp', 'atk' => 'ev_atk', 'def' => 'ev_def', 'spa' => 'ev_spa', 'spd' => 'ev_spd', 'spe' => 'ev_spe'] as $key => $col) {
                    $v = (int) ($row->{$col} ?? 0);
                    if ($v > 0) {
                        $evs[$key] = $v;
                    }
                }

                $ordered[] = [
                    'league_pokemon_id' => $row->league_pokemon_id !== null ? (int) $row->league_pokemon_id : null,
                    'ability' => $row->ability ?? '',
                    'moves' => $moves,
                    'version_group_held_item_id' => $row->version_group_held_item_id !== null ? (int) $row->version_group_held_item_id : null,
                    'nature' => $row->nature !== null ? (int) $row->nature : null,
                    'tera_type' => $row->tera_type,
                    'evs' => $evs === [] ? null : $evs,
                ];
            }

            DB::table('set_team_pokepastes')->where('id', $paste->id)->update([
                'slots' => json_encode($ordered),
            ]);
        }

        Schema::dropIfExists('set_team_pokepaste_slots');
    }
};

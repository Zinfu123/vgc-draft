<?php

namespace App\Modules\MatchPrep\Actions;

use App\Models\User;
use App\Modules\Matches\Models\Set;
use App\Modules\MatchPrep\Models\MatchPrepNote;
use App\Modules\Teams\Models\Team;

class UpsertMatchPrepNoteAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $user, Set $set, array $data): MatchPrepNote
    {
        $this->assertUserParticipatesInSet($user, $set);

        $note = MatchPrepNote::query()->firstOrNew([
            'user_id' => $user->id,
            'set_id' => $set->id,
        ]);

        $note->bring_six_slots = $data['bring_six_slots'];
        $note->plan_1_slots = $data['plan_1_slots'];
        $note->plan_2_slots = $data['plan_2_slots'];
        $note->plan_3_slots = $data['plan_3_slots'];
        $note->plan_1_notes = $data['plan_1_notes'] ?? null;
        $note->plan_2_notes = $data['plan_2_notes'] ?? null;
        $note->plan_3_notes = $data['plan_3_notes'] ?? null;
        $note->calcs = $this->sanitizeCalcs($data['calcs'] ?? []);

        $note->save();

        return $note->fresh();
    }

    public function assertUserParticipatesInSet(User $user, Set $set): void
    {
        $onSet = $set->team1_id !== null && $set->team2_id !== null
            && Team::query()
                ->where('user_id', $user->id)
                ->where('league_id', $set->league_id)
                ->whereIn('id', [(int) $set->team1_id, (int) $set->team2_id])
                ->exists();

        abort_unless($onSet, 403);
    }

    /**
     * @param  array<int, mixed>  $calcs
     * @return list<array{my_league_pokemon_id: int, opponent_league_pokemon_id: int, body: string}>
     */
    private function sanitizeCalcs(array $calcs): array
    {
        $out = [];
        foreach ($calcs as $c) {
            if (! is_array($c)) {
                continue;
            }
            if (! isset($c['my_league_pokemon_id'], $c['opponent_league_pokemon_id'])) {
                continue;
            }
            if (! is_numeric($c['my_league_pokemon_id']) || ! is_numeric($c['opponent_league_pokemon_id'])) {
                continue;
            }
            $out[] = [
                'my_league_pokemon_id' => (int) $c['my_league_pokemon_id'],
                'opponent_league_pokemon_id' => (int) $c['opponent_league_pokemon_id'],
                'body' => (string) ($c['body'] ?? ''),
            ];
        }

        return $out;
    }
}

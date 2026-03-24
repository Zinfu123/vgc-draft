<?php

namespace App\Modules\MatchPrep\Support;

use App\Modules\MatchPrep\Models\MatchPrepNote;

class MatchPrepNotePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function forNote(?MatchPrepNote $note): array
    {
        if ($note === null) {
            return [
                'id' => null,
                'bring_six_slots' => MatchPrepNote::defaultBringSix(),
                'plan_1_slots' => MatchPrepNote::defaultPlanSlots(),
                'plan_2_slots' => MatchPrepNote::defaultPlanSlots(),
                'plan_3_slots' => MatchPrepNote::defaultPlanSlots(),
                'plan_1_notes' => '',
                'plan_2_notes' => '',
                'plan_3_notes' => '',
                'calcs' => [],
                'share_enabled' => false,
                'share_uuid' => null,
            ];
        }

        return [
            'id' => $note->id,
            'bring_six_slots' => self::normalizeBringSix($note->bring_six_slots),
            'plan_1_slots' => self::normalizePlanFour($note->plan_1_slots),
            'plan_2_slots' => self::normalizePlanFour($note->plan_2_slots),
            'plan_3_slots' => self::normalizePlanFour($note->plan_3_slots),
            'plan_1_notes' => (string) ($note->plan_1_notes ?? ''),
            'plan_2_notes' => (string) ($note->plan_2_notes ?? ''),
            'plan_3_notes' => (string) ($note->plan_3_notes ?? ''),
            'calcs' => self::normalizeCalcs($note->calcs),
            'share_enabled' => (bool) $note->share_enabled,
            'share_uuid' => $note->share_uuid,
        ];
    }

    /**
     * @return list<int|null>
     */
    private static function normalizeBringSix(mixed $raw): array
    {
        if (! is_array($raw)) {
            return MatchPrepNote::defaultBringSix();
        }
        $out = [];
        for ($i = 0; $i < 6; $i++) {
            $v = $raw[$i] ?? null;
            $out[] = is_numeric($v) ? (int) $v : null;
        }

        return $out;
    }

    /**
     * @return list<int|null>
     */
    private static function normalizePlanFour(mixed $raw): array
    {
        if (! is_array($raw)) {
            return MatchPrepNote::defaultPlanSlots();
        }
        $out = [];
        for ($i = 0; $i < 4; $i++) {
            $v = $raw[$i] ?? null;
            $out[] = is_numeric($v) ? (int) $v : null;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function normalizeCalcs(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $c) {
            if (! is_array($c)) {
                continue;
            }
            if (array_key_exists('my_league_pokemon_id', $c) || array_key_exists('opponent_league_pokemon_id', $c)) {
                $my = $c['my_league_pokemon_id'] ?? null;
                $opp = $c['opponent_league_pokemon_id'] ?? null;
                $out[] = [
                    'my_league_pokemon_id' => is_numeric($my) ? (int) $my : null,
                    'opponent_league_pokemon_id' => is_numeric($opp) ? (int) $opp : null,
                    'body' => (string) ($c['body'] ?? ''),
                ];
            } else {
                $out[] = [
                    'my_league_pokemon_id' => null,
                    'opponent_league_pokemon_id' => null,
                    'body' => (string) ($c['body'] ?? ''),
                    'legacy_title' => (string) ($c['title'] ?? ''),
                ];
            }
        }

        return $out;
    }
}

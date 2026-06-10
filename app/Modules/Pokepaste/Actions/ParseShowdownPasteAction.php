<?php

namespace App\Modules\Pokepaste\Actions;

use App\Kernel\Support\ShowdownFormatHelper;
use App\Modules\Matches\Models\Set;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Services\PokepasteSlotValidator;
use App\Modules\Pokepaste\Services\ShowdownPasteParser;
use App\Modules\Pokepaste\Services\VersionGroupHeldItemLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ParseShowdownPasteAction
{
    public function __construct(
        private ShowdownPasteParser $pasteParser,
        private PokepasteSlotValidator $slotValidator,
        private VersionGroupHeldItemLookup $heldItemLookup,
    ) {}

    public function __invoke(SetTeamPokepaste $pokepaste, string $paste): JsonResponse
    {
        $pokepaste->loadMissing(['matchable', 'team']);
        $matchable = $pokepaste->matchable;
        if ($matchable instanceof Set) {
            $matchable->loadMissing('league');
            $league = $matchable->league;
        } elseif ($matchable instanceof PlayoffMatch) {
            $matchable->loadMissing('playoff.league');
            $league = $matchable->playoff?->league;
        } else {
            $league = null;
        }
        $team = $pokepaste->team;

        if ($league === null || $team === null) {
            return response()->json([
                'ok' => false,
                'errors' => ['Match or league is missing.'],
            ], 422);
        }

        $versionGroup = $league->versionGroup();

        $parsed = $this->pasteParser->parse($paste);
        if ($parsed['errors'] !== []) {
            return response()->json([
                'ok' => false,
                'errors' => $parsed['errors'],
            ], 422);
        }

        $roster = $team->pokemon()
            ->with('pokemon')
            ->where('league_id', $league->id)
            ->get();

        $slots = [];
        $matchErrors = [];

        foreach ($parsed['blocks'] as $i => $block) {
            $match = $this->matchSpeciesToRoster($block['species_raw'], $roster);
            if ($match['error'] !== null) {
                $matchErrors[] = 'Set '.($i + 1).': '.$match['error'];

                continue;
            }

            $lp = $match['league_pokemon'];
            if ($lp === null
                || (int) $lp->league_id !== (int) $league->id
                || (int) $lp->drafted_by !== (int) $team->id) {
                $matchErrors[] = 'Set '.($i + 1).': That Pokémon is not on your roster for this league.';

                continue;
            }

            $heldItemId = null;
            if ($versionGroup !== null && $block['item'] !== null && trim($block['item']) !== '') {
                $heldItemId = $this->heldItemLookup->findIdByShowdownLabel($versionGroup, $block['item']);
                if ($heldItemId === null) {
                    $matchErrors[] = 'Set '.($i + 1).': Unknown held item for this game version: '.$block['item'];

                    continue;
                }
            }

            $slots[] = [
                'league_pokemon_id' => $lp->id,
                'ability' => $block['ability'],
                'moves' => array_map(
                    fn (string $m) => ShowdownFormatHelper::moveToSlug($m),
                    $block['moves']
                ),
                'version_group_held_item_id' => $heldItemId,
                'nature' => $block['nature'],
                'tera_type' => $block['tera_type'],
                'evs' => $block['evs'],
            ];
        }

        if ($matchErrors !== []) {
            return response()->json([
                'ok' => false,
                'errors' => $matchErrors,
            ], 422);
        }

        if (count($slots) !== 6) {
            return response()->json([
                'ok' => false,
                'errors' => ['Could not build six slots from paste.'],
            ], 422);
        }

        try {
            $normalized = $this->slotValidator->validateAndNormalize($team, $league, $slots);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'slots' => $normalized,
        ]);
    }

    /**
     * @param  Collection<int, \App\Modules\League\Models\LeaguePokemon>  $roster
     * @return array{error: ?string, league_pokemon: ?\App\Modules\League\Models\LeaguePokemon}
     */
    private function matchSpeciesToRoster(string $speciesRaw, Collection $roster): array
    {
        $key = ShowdownFormatHelper::speciesToMatchKey($speciesRaw);
        $matches = [];

        foreach ($roster as $lp) {
            $candidates = array_filter([$lp->pokemon?->name, $lp->name]);
            foreach ($candidates as $name) {
                if (ShowdownFormatHelper::speciesToMatchKey((string) $name) === $key) {
                    $matches[$lp->id] = $lp;
                    break;
                }
            }
        }

        $unique = collect($matches)->values();
        if ($unique->isEmpty()) {
            return [
                'error' => 'Species not on your roster: '.$speciesRaw,
                'league_pokemon' => null,
            ];
        }

        if ($unique->count() > 1) {
            return [
                'error' => 'Multiple roster Pokémon match this species; assign slots manually.',
                'league_pokemon' => null,
            ];
        }

        return [
            'error' => null,
            'league_pokemon' => $unique->first(),
        ];
    }
}

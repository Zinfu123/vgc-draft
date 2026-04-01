<?php

namespace App\Modules\DamageCalculator\Controllers;

use App\Enums\PokemonNature;
use App\Http\Controllers\Controller;
use App\Http\Requests\DamageCalculator\DamageCalculateRequest;
use App\Http\Requests\TeamCoverage\TeamCoverageLearnsetRequest;
use App\Http\Requests\TeamCoverage\TeamCoveragePokedexSearchRequest;
use App\Modules\DamageCalculator\Services\Gen9DamageEngine;
use App\Modules\DamageCalculator\Services\MechanicsProfileResolver;
use App\Modules\DamageCalculator\Services\MoveDataResolver;
use App\Modules\DamageCalculator\ValueObjects\BattleParticipant;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\Set;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokedexFilterService;
use App\Modules\Pokedex\Services\TypeEffectivenessTable;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Stats\Models\VgcLadderSpeciesUsage;
use App\Modules\TeamCoverage\Controllers\TeamCoveragePlannerController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DamageCalculatorController extends Controller
{
    public function show(Request $request): Response
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $versionGroups = VersionGroup::query()
            ->orderByDesc('sort_order')
            ->get(['id', 'slug', 'name', 'generation', 'sort_order', 'showdown_format_key']);

        $defaultSlug = (string) config('pokemon.default_version_group_slug');
        $selectedGroup = $versionGroups->firstWhere('slug', $defaultSlug)
            ?? $versionGroups->first();

        $matchContext = $this->matchContextFromRequest($request);

        return Inertia::render('tools/DamageCalculator', [
            'versionGroups' => $versionGroups,
            'defaultVersionSlug' => $selectedGroup?->slug ?? $defaultSlug,
            'typeOrder' => TypeEffectivenessTable::TYPE_ORDER,
            'natures' => PokemonNature::optionsForFrontend(),
            'matchContext' => $matchContext,
        ]);
    }

    public function search(
        TeamCoveragePokedexSearchRequest $request,
        PokedexFilterService $pokedexFilterService,
    ): JsonResponse {
        return app(TeamCoveragePlannerController::class)->search($request, $pokedexFilterService);
    }

    public function learnset(
        TeamCoverageLearnsetRequest $request,
        Pokedex $pokedex,
    ): JsonResponse {
        return app(TeamCoveragePlannerController::class)->learnset($request, $pokedex);
    }

    public function calculate(
        DamageCalculateRequest $request,
        MechanicsProfileResolver $mechanicsResolver,
        MoveDataResolver $moveResolver,
        Gen9DamageEngine $engine,
    ): JsonResponse {
        $data = $request->validated();
        $versionGroup = VersionGroup::query()->where('slug', $data['version_group_slug'])->first();
        if ($versionGroup === null) {
            return response()->json(['message' => 'Unknown version group.'], 422);
        }

        $attackerGd = PokemonGenerationData::query()
            ->where('pokedex_id', $data['attacker']['pokedex_id'])
            ->where('version_group_id', $versionGroup->id)
            ->first();
        $defenderGd = PokemonGenerationData::query()
            ->where('pokedex_id', $data['defender']['pokedex_id'])
            ->where('version_group_id', $versionGroup->id)
            ->first();

        if ($attackerGd === null || $defenderGd === null) {
            return response()->json(['message' => 'Pokémon data not found for this version group.'], 422);
        }

        $move = $moveResolver->resolve($versionGroup, (int) $data['move_id']);
        if ($move === null) {
            return response()->json(['message' => 'Move not found.'], 422);
        }

        $mechanics = $mechanicsResolver->resolve($versionGroup);
        $types = TypeEffectivenessTable::forChart($mechanics->typeChartId);

        $attacker = $this->participantFromPayload($data['attacker'], $attackerGd);
        $defender = $this->participantFromPayload($data['defender'], $defenderGd);

        $result = $engine->damage($attacker, $defender, $move, $mechanics, $types);

        return response()->json([
            'damage' => $result,
            'move' => [
                'name' => $move->name,
                'type_slug' => $move->typeSlug,
                'damage_class' => $move->damageClass,
                'power' => $move->power,
            ],
        ]);
    }

    public function vgcUsage(Request $request): JsonResponse
    {
        $slug = (string) $request->query('version_group_slug', config('pokemon.default_version_group_slug'));
        $speciesKey = strtolower(trim((string) $request->query('species_key', '')));
        if ($speciesKey === '') {
            return response()->json(['row' => null]);
        }

        $versionGroup = VersionGroup::query()->where('slug', $slug)->first();
        if ($versionGroup === null) {
            return response()->json(['row' => null]);
        }

        $formatKey = $versionGroup->showdown_format_key;
        if ($formatKey === null || $formatKey === '') {
            return response()->json([
                'row' => null,
                'message' => 'No Showdown format configured for this version group.',
            ]);
        }

        if (! $this->isAllowedVgcFormatKey($formatKey)) {
            return response()->json(['row' => null, 'message' => 'Invalid format key.'], 422);
        }

        $period = VgcLadderSpeciesUsage::query()
            ->where('format_key', $formatKey)
            ->max('period');

        if ($period === null) {
            return response()->json(['row' => null, 'format_key' => $formatKey]);
        }

        $row = VgcLadderSpeciesUsage::query()
            ->where('format_key', $formatKey)
            ->where('period', $period)
            ->where('species_key', $speciesKey)
            ->first();

        return response()->json([
            'row' => $row === null ? null : [
                'usage_percent' => (float) $row->usage_percent,
                'detail' => $row->detail,
                'period' => $row->period,
            ],
            'format_key' => $formatKey,
            'period' => $period,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function matchContextFromRequest(Request $request): ?array
    {
        $leagueId = $request->query('league_id');
        $setId = $request->query('set_id');
        $teamSide = $request->query('team');
        $playoffMatchId = $request->query('playoff_match_id');

        if ($playoffMatchId !== null && is_numeric($playoffMatchId)) {
            $pm = PlayoffMatch::query()->with('playoff.league')->find((int) $playoffMatchId);
            if ($pm === null) {
                return null;
            }
            $leagueFromPm = $pm->playoff?->league;
            if ($leagueFromPm !== null && $leagueId !== null && is_numeric($leagueId)
                && (int) $leagueId !== (int) $leagueFromPm->id) {
                return null;
            }
            $side = is_numeric($teamSide) ? (int) $teamSide : 1;
            $teamId = $side === 2 ? $pm->team2_id : $pm->team1_id;
            if ($teamId === null) {
                return null;
            }
            $paste = SetTeamPokepaste::query()
                ->where('matchable_type', PlayoffMatch::class)
                ->where('matchable_id', $pm->id)
                ->where('team_id', $teamId)
                ->with(['pasteSlots' => fn ($q) => $q->orderBy('slot_index'), 'pasteSlots.leaguePokemon.pokemon'])
                ->first();

            return $this->matchContextPayload($paste, $leagueFromPm, null, $pm->id, $side);
        }

        if ($leagueId !== null && is_numeric($leagueId) && $setId !== null && is_numeric($setId) && $teamSide !== null && is_numeric($teamSide)) {
            $set = Set::query()->with('league')->where('id', (int) $setId)->where('league_id', (int) $leagueId)->first();
            if ($set === null) {
                return null;
            }
            $side = (int) $teamSide === 2 ? 2 : 1;
            $teamId = $side === 2 ? $set->team2_id : $set->team1_id;
            if ($teamId === null) {
                return null;
            }
            $paste = SetTeamPokepaste::query()
                ->where('matchable_type', Set::class)
                ->where('matchable_id', $set->id)
                ->where('team_id', $teamId)
                ->with(['pasteSlots' => fn ($q) => $q->orderBy('slot_index'), 'pasteSlots.leaguePokemon.pokemon'])
                ->first();

            return $this->matchContextPayload($paste, $set->league, $set->id, null, $side);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function matchContextPayload(
        ?SetTeamPokepaste $paste,
        ?League $league,
        ?int $setId,
        ?int $playoffMatchId,
        int $teamSide,
    ): ?array {
        if ($paste === null || $league === null) {
            return null;
        }

        $versionGroup = $league->versionGroup();
        $vgSlug = $versionGroup?->slug ?? (string) config('pokemon.default_version_group_slug');

        $slots = [];
        foreach ($paste->pasteSlots as $slot) {
            $lp = $slot->leaguePokemon;
            $dex = $lp?->pokemon;
            $pokedexId = $dex?->id;
            $gd = null;
            if ($pokedexId !== null && $versionGroup !== null) {
                $gd = PokemonGenerationData::query()
                    ->where('pokedex_id', $pokedexId)
                    ->where('version_group_id', $versionGroup->id)
                    ->first();
            }
            $slots[] = [
                'slot_index' => (int) $slot->slot_index,
                'pokedex_id' => $pokedexId,
                'name' => (string) ($dex?->name ?? ''),
                'sprite_url' => $dex?->sprite_url,
                'type1' => (string) ($gd?->type1 ?? $dex?->type1 ?? ''),
                'type2' => $gd?->type2 ?? $dex?->type2,
                'paste' => $slot->toFrontendSlotArray(),
            ];
        }

        return [
            'league_id' => (int) $league->id,
            'set_id' => $setId,
            'playoff_match_id' => $playoffMatchId,
            'team' => $teamSide,
            'version_group_slug' => $vgSlug,
            'slots' => $slots,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function participantFromPayload(array $payload, PokemonGenerationData $gd): BattleParticipant
    {
        $nature = PokemonNature::from((int) $payload['nature']);
        $evIn = is_array($payload['ev'] ?? null) ? $payload['ev'] : [];
        $ivIn = is_array($payload['iv'] ?? null) ? $payload['iv'] : [];

        $evs = [
            'hp' => (int) ($evIn['hp'] ?? 0),
            'atk' => (int) ($evIn['atk'] ?? 0),
            'def' => (int) ($evIn['def'] ?? 0),
            'spa' => (int) ($evIn['spa'] ?? 0),
            'spd' => (int) ($evIn['spd'] ?? 0),
            'spe' => (int) ($evIn['spe'] ?? 0),
        ];
        $ivs = [
            'hp' => (int) ($ivIn['hp'] ?? 31),
            'atk' => (int) ($ivIn['atk'] ?? 31),
            'def' => (int) ($ivIn['def'] ?? 31),
            'spa' => (int) ($ivIn['spa'] ?? 31),
            'spd' => (int) ($ivIn['spd'] ?? 31),
            'spe' => (int) ($ivIn['spe'] ?? 31),
        ];

        $item = isset($payload['item']) && is_string($payload['item']) ? strtolower(trim($payload['item'])) : '';
        if ($item === '') {
            $item = 'none';
        }

        return new BattleParticipant(
            baseStats: [
                'hp' => (int) $gd->hp,
                'atk' => (int) $gd->atk,
                'def' => (int) $gd->def,
                'spa' => (int) $gd->spa,
                'spd' => (int) $gd->spd,
                'spe' => (int) $gd->spe,
            ],
            level: (int) $payload['level'],
            nature: $nature,
            type1: (string) $gd->type1,
            type2: $gd->type2,
            teraType: isset($payload['tera_type']) && is_string($payload['tera_type']) ? $payload['tera_type'] : null,
            terastallized: (bool) ($payload['terastallized'] ?? false),
            burned: (bool) ($payload['burned'] ?? false),
            item: $item,
            evs: $evs,
            ivs: $ivs,
        );
    }

    private function isAllowedVgcFormatKey(string $formatKey): bool
    {
        $key = strtolower($formatKey);
        foreach (config('showdown_vgc.allowed_format_substrings', ['vgc']) as $sub) {
            if (str_contains($key, strtolower((string) $sub))) {
                return true;
            }
        }

        $exact = config('showdown_vgc.allowed_format_keys', []);
        if (is_array($exact) && in_array($formatKey, $exact, true)) {
            return true;
        }

        return false;
    }
}

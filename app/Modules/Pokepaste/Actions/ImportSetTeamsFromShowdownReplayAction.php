<?php

namespace App\Modules\Pokepaste\Actions;

use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\Set;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Models\SetTeamPokepasteSlot;
use App\Modules\Pokepaste\Services\EnsureSetTeamPokepasteSlotRows;
use App\Modules\Pokepaste\Services\PokepasteSlotValidator;
use App\Modules\Pokepaste\Services\ShowdownFormatHelper;
use App\Modules\Pokepaste\Services\ShowdownReplayLogFetcher;
use App\Modules\Pokepaste\Services\ShowdownReplayLogUrl;
use App\Modules\Pokepaste\Services\ShowdownReplayTeamPreviewParser;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class ImportSetTeamsFromShowdownReplayAction
{
    public function __construct(
        private ShowdownReplayLogFetcher $logFetcher,
        private ShowdownReplayTeamPreviewParser $previewParser,
        private PokepasteSlotValidator $slotValidator,
        private EnsureSetTeamPokepasteSlotRows $ensureSlotRows,
    ) {}

    public function __invoke(Set $set, int $replaySlot, int $p1TeamId): RedirectResponse
    {
        $set->refresh();

        $replayUrl = match ($replaySlot) {
            1 => $set->replay1,
            2 => $set->replay2,
            3 => $set->replay3,
            default => null,
        };

        if ($replayUrl === null || trim((string) $replayUrl) === '') {
            return redirect()
                ->route('sets.show', ['set_id' => $set->id])
                ->withErrors(['replay_import' => 'No replay URL for the selected game.']);
        }

        try {
            $logUrl = ShowdownReplayLogUrl::resolveLogDownloadUrl($replayUrl);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('sets.show', ['set_id' => $set->id])
                ->withErrors(['replay_import' => $e->getMessage()]);
        }

        try {
            $logText = $this->logFetcher->fetch($logUrl);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('sets.show', ['set_id' => $set->id])
                ->withErrors(['replay_import' => $e->getMessage()]);
        }

        $preview = $this->previewParser->parse($logText);
        if ($preview['errors'] !== []) {
            return redirect()
                ->route('sets.show', ['set_id' => $set->id])
                ->withErrors(['replay_import' => implode(' ', $preview['errors'])]);
        }

        $league = League::query()->find($set->league_id);
        if ($league === null) {
            return redirect()
                ->route('sets.show', ['set_id' => $set->id])
                ->withErrors(['replay_import' => 'League not found for this match.']);
        }

        $teamP1 = Team::query()->find($p1TeamId);
        if ($teamP1 === null) {
            return redirect()
                ->route('sets.show', ['set_id' => $set->id])
                ->withErrors(['replay_import' => 'Team not found.']);
        }

        $teamP2Id = $p1TeamId === (int) $set->team1_id ? (int) $set->team2_id : (int) $set->team1_id;
        $teamP2 = Team::query()->find($teamP2Id);
        if ($teamP2 === null) {
            return redirect()
                ->route('sets.show', ['set_id' => $set->id])
                ->withErrors(['replay_import' => 'Opposing team not found.']);
        }

        $build = $this->buildPartialSlotsForTeam($teamP1, $league, $preview['p1']);
        if ($build['errors'] !== '') {
            return redirect()
                ->route('sets.show', ['set_id' => $set->id])
                ->withErrors(['replay_import' => $build['errors']]);
        }

        $build2 = $this->buildPartialSlotsForTeam($teamP2, $league, $preview['p2']);
        if ($build2['errors'] !== '') {
            return redirect()
                ->route('sets.show', ['set_id' => $set->id])
                ->withErrors(['replay_import' => $build2['errors']]);
        }

        try {
            DB::transaction(function () use ($set, $league, $teamP1, $teamP2, $build, $build2): void {
                $this->persistSlotsForTeamOnSet($set, $league, $teamP1, $build['slots']);
                $this->persistSlotsForTeamOnSet($set, $league, $teamP2, $build2['slots']);
            });
        } catch (ValidationException $e) {
            return redirect()
                ->route('sets.show', ['set_id' => $set->id])
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('sets.show', ['set_id' => $set->id])
            ->with('success', 'Imported team species from the Showdown replay for both teams. Finish abilities, moves, and items in each team paste.');
    }

    /**
     * @param  list<string>  $speciesRaws
     * @return array{errors: string, slots?: array<int, array<string, mixed>>}
     */
    private function buildPartialSlotsForTeam(Team $team, League $league, array $speciesRaws): array
    {
        $roster = $team->pokemon()
            ->with('pokemon')
            ->where('league_id', $league->id)
            ->get();

        $slots = [];
        $errors = [];

        foreach ($speciesRaws as $i => $speciesRaw) {
            $match = $this->matchSpeciesToRoster($speciesRaw, $roster);
            if ($match['error'] !== null) {
                $errors[] = 'Set '.($i + 1).' ('.$team->name.'): '.$match['error'];

                continue;
            }

            $lp = $match['league_pokemon'];
            if ($lp === null
                || (int) $lp->league_id !== (int) $league->id
                || (int) $lp->drafted_by !== (int) $team->id) {
                $errors[] = 'Set '.($i + 1).' ('.$team->name.'): That Pokémon is not on this team\'s roster for the league.';

                continue;
            }

            $slots[] = [
                'league_pokemon_id' => $lp->id,
                'ability' => '',
                'moves' => ['', '', '', ''],
                'version_group_held_item_id' => null,
                'nature' => null,
                'tera_type' => null,
                'evs' => null,
            ];
        }

        if ($errors !== []) {
            return ['errors' => implode(' ', $errors)];
        }

        if (count($slots) !== 6) {
            return ['errors' => 'Could not build six roster slots from the replay for '.$team->name.'.'];
        }

        return ['errors' => '', 'slots' => $slots];
    }

    /**
     * @param  array<int, array<string, mixed>>  $slots
     */
    private function persistSlotsForTeamOnSet(Set $set, League $league, Team $team, array $slots): void
    {
        $paste = SetTeamPokepaste::query()->firstOrCreate(
            [
                'matchable_type' => Set::class,
                'matchable_id' => $set->id,
                'team_id' => $team->id,
            ],
        );

        ($this->ensureSlotRows)($paste);

        $normalized = $this->slotValidator->validateAndNormalize($team, $league, $slots, allowPartialSave: true);

        foreach ($normalized as $index => $slot) {
            SetTeamPokepasteSlot::query()->updateOrCreate(
                [
                    'set_team_pokepaste_id' => $paste->id,
                    'slot_index' => $index,
                ],
                SetTeamPokepasteSlot::attributesFromNormalizedSlot($slot)
            );
        }
    }

    /**
     * @param  Collection<int, LeaguePokemon>  $roster
     * @return array{error: ?string, league_pokemon: ?LeaguePokemon}
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
                'error' => 'Species not on roster: '.$speciesRaw,
                'league_pokemon' => null,
            ];
        }

        if ($unique->count() > 1) {
            return [
                'error' => 'Multiple roster Pokémon match species '.$speciesRaw.'; remove duplicates or edit manually.',
                'league_pokemon' => null,
            ];
        }

        return [
            'error' => null,
            'league_pokemon' => $unique->first(),
        ];
    }
}

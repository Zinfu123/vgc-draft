<?php

namespace App\Modules\Matches\Actions;

use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Models\SetGameResult;
use App\Modules\Pokepaste\Services\ShowdownFormatHelper;
use App\Modules\Pokepaste\Services\ShowdownReplayKoParser;
use App\Modules\Pokepaste\Services\ShowdownReplayLogFetcher;
use App\Modules\Pokepaste\Services\ShowdownReplayLogUrl;
use App\Modules\Pokepaste\Services\ShowdownReplayPlayerNamesParser;
use App\Modules\Pokepaste\Services\ShowdownReplaySelectedPokemonParser;
use App\Modules\Pokepaste\Services\ShowdownReplayWinnerParser;
use App\Modules\Pokepaste\Services\ShowdownUsernameNormalizer;
use App\Modules\Pokepaste\Services\SuggestP1TeamFromShowdownReplay;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Log;

class ParseSetGameResultsFromReplaysAction
{
    public function __construct(
        private ShowdownReplayLogFetcher $logFetcher,
        private ShowdownReplayPlayerNamesParser $playerNamesParser,
        private ShowdownReplayWinnerParser $winnerParser,
        private ShowdownReplaySelectedPokemonParser $selectedParser,
        private ShowdownReplayKoParser $koParser,
        private SuggestP1TeamFromShowdownReplay $suggestP1Team,
    ) {}

    public function __invoke(Set $set): void
    {
        $set->loadMissing(['team1.user', 'team2.user']);

        $slots = [
            1 => $set->replay1,
            2 => $set->replay2,
            3 => $set->replay3,
        ];

        foreach ($slots as $gameNumber => $replayUrl) {
            if (! is_string($replayUrl) || trim($replayUrl) === '') {
                continue;
            }

            try {
                $logUrl = ShowdownReplayLogUrl::resolveLogDownloadUrl($replayUrl);
                $logText = $this->logFetcher->fetch($logUrl);
            } catch (\Throwable $e) {
                Log::warning("SetGameResult: could not fetch replay {$replayUrl} for set {$set->id}: {$e->getMessage()}");

                continue;
            }

            $playerNames = $this->playerNamesParser->parse($logText);
            if ($playerNames['errors'] !== []) {
                continue;
            }

            $p1TeamId = $this->suggestP1Team->suggest($set, $playerNames['p1'], $playerNames['p2']);
            if ($p1TeamId === null) {
                Log::warning("SetGameResult: could not resolve p1 team for replay slot {$gameNumber} on set {$set->id}");

                continue;
            }

            $p2TeamId = $p1TeamId === (int) $set->team1_id ? (int) $set->team2_id : (int) $set->team1_id;

            $winnerParsed = $this->winnerParser->parse($logText);
            if ($winnerParsed['errors'] !== [] || $winnerParsed['is_tie']) {
                $winnerTeamId = null;
            } else {
                $winnerTeamId = $this->resolveWinnerTeamId(
                    $set,
                    $p1TeamId,
                    $p2TeamId,
                    $winnerParsed['winner'],
                );
            }

            $selected = $this->selectedParser->parse($logText);
            $knockouts = $this->koParser->parse($logText);

            $p1Pokemon = $this->mapSpeciesToDexIds($selected['p1'], $p1TeamId, (int) $set->league_id);
            $p2Pokemon = $this->mapSpeciesToDexIds($selected['p2'], $p2TeamId, (int) $set->league_id);
            $p1Knockouts = $this->mapSpeciesToDexIds($knockouts['p1'], $p1TeamId, (int) $set->league_id);
            $p2Knockouts = $this->mapSpeciesToDexIds($knockouts['p2'], $p2TeamId, (int) $set->league_id);

            SetGameResult::updateOrCreate(
                ['set_id' => $set->id, 'game_number' => $gameNumber],
                [
                    'p1_team_id' => $p1TeamId,
                    'p2_team_id' => $p2TeamId,
                    'winner_team_id' => $winnerTeamId,
                    'p1_pokemon' => $p1Pokemon,
                    'p2_pokemon' => $p2Pokemon,
                    'p1_knockouts' => $p1Knockouts,
                    'p2_knockouts' => $p2Knockouts,
                ]
            );
        }
    }

    private function resolveWinnerTeamId(Set $set, int $p1TeamId, int $p2TeamId, string $winnerName): ?int
    {
        $normalized = ShowdownUsernameNormalizer::normalize($winnerName);

        $p1Team = $p1TeamId === (int) $set->team1_id ? $set->team1 : $set->team2;
        $p1Username = ShowdownUsernameNormalizer::normalize($p1Team?->effectiveShowdownUsername());

        if ($normalized !== null && $p1Username !== null && $normalized === $p1Username) {
            return $p1TeamId;
        }

        return $p2TeamId;
    }

    /**
     * Map species names (from |switch| lines) to pokedex_ids via the team's draft roster.
     * Species not found on the roster are silently skipped.
     *
     * @param  list<string>  $species
     * @return list<int>
     */
    private function mapSpeciesToDexIds(array $species, int $teamId, int $leagueId): array
    {
        $roster = LeaguePokemon::query()
            ->where('drafted_by', $teamId)
            ->where('league_id', $leagueId)
            ->with('pokemon:id,name,nationaldex_id')
            ->get();

        $dexIds = [];
        foreach ($species as $raw) {
            $key = ShowdownFormatHelper::speciesToMatchKey($raw);
            foreach ($roster as $lp) {
                $candidates = array_filter([$lp->pokemon?->name, $lp->name]);
                foreach ($candidates as $name) {
                    if (ShowdownFormatHelper::speciesToMatchKey((string) $name) === $key) {
                        if ($lp->pokedex_id !== null) {
                            $dexIds[] = (int) $lp->pokedex_id;
                        }
                        break 2;
                    }
                }
            }
        }

        return $dexIds;
    }
}

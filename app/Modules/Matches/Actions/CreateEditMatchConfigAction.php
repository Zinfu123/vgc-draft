<?php

namespace App\Modules\Matches\Actions;

/* Define Models */
use App\Modules\Matches\Models\MatchConfig;

/* End Define Models */

/* Define Dependencies */
/* End Define Dependencies */

class CreateEditMatchConfigAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'create') {
            $matchConfig = MatchConfig::create([
                'league_id' => $data['league_id'],
                'number_of_pools' => $data['number_of_pools'],
                'frequency_type' => $data['frequency_type'],
                'minimum_drafts' => $data['minimum_drafts'],
            ]);

            return $matchConfig;
        } elseif ($data['command'] == 'update') {
            $matchConfig = MatchConfig::where('league_id', $data['league_id'])->first();
            if (! $matchConfig) {
                throw new \Exception('Match config not found for this league. Please create a match config first.');
            }
            $matchConfig->number_of_pools = $data['number_of_pools'];
            $matchConfig->frequency_type = $data['frequency_type'];
            $matchConfig->require_team_match_pokepaste_before_results = filter_var(
                $data['require_team_match_pokepaste_before_results'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );
            $matchConfig->require_replays_before_results = filter_var(
                $data['require_replays_before_results'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );
            $matchConfig->auto_complete_set_from_replays = filter_var(
                $data['auto_complete_set_from_replays'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );
            $matchConfig->save();
            redirect()->route('leagues.detail', ['league' => $data['league_id']]);

            return $matchConfig;
        } elseif ($data['command'] == 'show') {
            $matchConfig = MatchConfig::where('league_id', $data['league_id'])->first();

            return $matchConfig;
        }
    }
}

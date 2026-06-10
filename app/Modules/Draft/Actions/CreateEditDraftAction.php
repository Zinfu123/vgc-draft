<?php

namespace App\Modules\Draft\Actions;

use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Bans;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\League\Enums\LeagueStagingStatus;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Teams\Models\Team;
use App\Notifications\DraftEndedNotification;
use Carbon\Carbon;

class CreateEditDraftAction
{
    public function __invoke($data)
    {
        // Create Draft
        if ($data['command'] == 'create') {
            if (League::with('draftConfig')->find($data['league_id'])->draftConfig->ban_enabled == true) {
                $status = 2;
            } else {
                $status = 1;
            }
            $draft = Draft::create([
                'league_id' => $data['league_id'],
                'round_number' => 1,
                'status' => $status,
                'pick_number' => 1,
            ]);
            $draft->save();
            $league = League::where('id', $data['league_id'])->first();
            $league->open = false;
            $league->status = LeagueStatus::Staging;
            $league->staging_sub_status = LeagueStagingStatus::DraftInProgress;
            $league->save();

            activity()
                ->performedOn($draft)
                ->withProperties(['league_id' => $data['league_id']])
                ->log('Draft started');

            return $draft;
        }
        // Create Ban Placeholders
        elseif ($data['command'] == 'create_ban') {
            $draftConfig = League::with('draftConfig')->find($data['league_id'])->draftConfig;
            $teams = Team::where('league_id', $data['league_id'])->get();

            for ($round = 1; $round <= $draftConfig->bans_per_user; $round++) {
                foreach ($teams as $team) {
                    Bans::create([
                        'league_id' => $data['league_id'],
                        'team_id' => $team->id,
                        'round_number' => $round,
                        'status' => 0,
                    ]);
                }
            }
        }
        // Next Round
        elseif ($data['command'] == 'next_round') {
            $draft = Draft::where('league_id', $data['league_id'])->first();
            $draft->round_number++;
            $draft->save();

            (new CreateEditDraftOrderAction)->__invoke([
                'league_id' => $data['league_id'],
            ]);
        }
        // End Draft
        elseif ($data['command'] == 'finalize_draft') {
            $draft = Draft::where('league_id', $data['league_id'])->first();
            $draft->status = 0;
            $draft->save();

            $this->adjustSetStartDateIfNeeded((int) $data['league_id']);

            $league = League::with('draftConfig')->find($data['league_id']);

            if ($league !== null) {
                $draftConfig = $league->draftConfig;

                if ($draftConfig !== null) {
                    $draftConfig->draft_ended_at = Carbon::now();
                    $draftConfig->save();
                }

                $league->status = LeagueStatus::Staging;
                $league->staging_sub_status = LeagueStagingStatus::FreeTradeWindow;
                $league->save();

                $league->notify(new DraftEndedNotification($league));
            }

            activity()
                ->performedOn($draft)
                ->withProperties(['league_id' => $data['league_id']])
                ->log('Draft finalized');
        } elseif ($data['command'] == 'revert_last_pick') {
            $this->revertLastAction((int) $data['league_id']);
        }
        // Abort Draft
        elseif ($data['command'] == 'abort_draft') {

            $draftBeforeAbort = Draft::where('league_id', $data['league_id'])->first();

            activity()
                ->performedOn($draftBeforeAbort)
                ->withProperties(['league_id' => $data['league_id']])
                ->log('Draft aborted');

            $draft = Draft::where('league_id', $data['league_id'])->get();
            foreach ($draft as $draft) {
                $draft->delete();
            }

            $draftPicks = DraftPick::where('league_id', $data['league_id'])->get();
            foreach ($draftPicks as $draftPick) {
                $draftPick->delete();
            }

            $bans = Bans::where('league_id', $data['league_id'])->get();
            foreach ($bans as $ban) {
                $ban->delete();
            }

            $banOrders = BanOrder::where('league_id', $data['league_id'])->get();
            foreach ($banOrders as $banOrder) {
                $banOrder->delete();
            }

            $leaguePokemon = LeaguePokemon::where('league_id', $data['league_id'])
                ->where(fn ($q) => $q->where('is_drafted', 1)->orWhere('banned', true))
                ->get();
            foreach ($leaguePokemon as $pokemon) {
                $pokemon->is_drafted = 0;
                $pokemon->drafted_by = null;
                $pokemon->banned = false;
                $pokemon->save();
            }

            $draftOrder = DraftOrder::where('league_id', $data['league_id'])->get();
            foreach ($draftOrder as $order) {
                $order->delete();
            }

            $draftPoints = League::with('draftConfig')->find($data['league_id'])->draftConfig->draft_points;
            $teams = Team::where('league_id', $data['league_id'])->get();
            foreach ($teams as $team) {
                $team->draft_points = $draftPoints;
                $team->save();
            }

            $league = League::with('draftConfig')->where('id', $data['league_id'])->first();
            $league->open = true;
            $league->status = LeagueStatus::Registration;
            $league->staging_sub_status = null;
            $draftConfig = $league->draftConfig;
            if ($draftConfig !== null) {
                $draftConfig->draft_ended_at = null;
                $draftConfig->save();
            }
            $league->save();
        }
        // Finalize draft also clears any timer state
        if ($data['command'] == 'finalize_draft') {
            (new DraftTimerAction)(['league_id' => $data['league_id'], 'command' => DraftTimerAction::COMMAND_CLEAR]);
        }
    }

    private function adjustSetStartDateIfNeeded(int $leagueId): void
    {
        $league = League::find($leagueId);
        if ($league === null) {
            return;
        }

        $today = Carbon::today()->toDateString();
        if ($league->set_start_date === null || $league->set_start_date < $today) {
            $league->set_start_date = $today;
            $league->save();
        }
    }

    /**
     * Revert whichever of (last pick | last draft-skip | last ban | last ban-skip)
     * happened most recently. Picks/bans are reverted in full; skips just flip the
     * order back to "your turn" and rewind the draft pointer.
     */
    private function revertLastAction(int $leagueId): void
    {
        $lastPick = DraftPick::query()
            ->where('league_id', $leagueId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $lastDraftSkip = DraftOrder::query()
            ->where('league_id', $leagueId)
            ->whereNotNull('skipped_at')
            ->orderBy('skipped_at', 'desc')
            ->first();

        $lastBan = Bans::query()
            ->where('league_id', $leagueId)
            ->whereNotNull('pokedex_id')
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $lastBanSkip = BanOrder::query()
            ->where('league_id', $leagueId)
            ->whereNotNull('skipped_at')
            ->orderBy('skipped_at', 'desc')
            ->first();

        $candidates = collect([
            ['type' => 'pick', 'at' => $lastPick?->created_at, 'model' => $lastPick],
            ['type' => 'draft_skip', 'at' => $lastDraftSkip?->skipped_at, 'model' => $lastDraftSkip],
            ['type' => 'ban', 'at' => $lastBan?->updated_at ?? $lastBan?->created_at, 'model' => $lastBan],
            ['type' => 'ban_skip', 'at' => $lastBanSkip?->skipped_at, 'model' => $lastBanSkip],
        ])->filter(fn ($c) => $c['at'] !== null && $c['model'] !== null);

        if ($candidates->isEmpty()) {
            return;
        }

        $winner = $candidates->sortByDesc(fn ($c) => $c['at']->getTimestamp())->first();

        match ($winner['type']) {
            'pick' => $this->revertLastPick($leagueId, $winner['model']),
            'draft_skip' => $this->revertLastDraftSkip($leagueId, $winner['model']),
            'ban' => $this->revertLastBan($leagueId, $winner['model']),
            'ban_skip' => $this->revertLastBanSkip($leagueId, $winner['model']),
        };
    }

    private function revertLastPick(int $leagueId, DraftPick $lastPick): void
    {
        $lastPickedPokemonId = $lastPick->league_pokemon_id;
        $teamId = $lastPick->team_id;
        $lastPick->delete();

        $pokemonReversion = LeaguePokemon::where('id', $lastPickedPokemonId)->first();
        if ($pokemonReversion !== null) {
            $pokemonReversion->is_drafted = 0;
            $pokemonReversion->drafted_by = null;
            $pokemonReversion->save();
        }

        $team = Team::where('id', $teamId)->first();
        if ($team !== null && $pokemonReversion !== null) {
            $team->draft_points = $team->draft_points + $pokemonReversion->cost;
            $team->save();
        }

        $draftOrder = DraftOrder::query()
            ->where('league_id', $leagueId)
            ->where('team_id', $teamId)
            ->where('status', 0)
            ->whereNull('skipped_at')
            ->orderBy('round_number', 'desc')
            ->orderBy('pick_number', 'desc')
            ->first();

        if ($draftOrder !== null) {
            $draftOrder->status = 1;
            $draftOrder->save();

            if ($lastPick->pick_number > 1 && $lastPick->round_number > 1) {
                $draft = Draft::where('league_id', $leagueId)->first();
                if ($draft !== null) {
                    $draft->pick_number = $draftOrder->pick_number;
                    $draft->round_number = $draftOrder->round_number;
                    $draft->save();
                }
            }
        }

        activity()
            ->withProperties([
                'league_id' => $leagueId,
                'team_id' => $teamId,
                'league_pokemon_id' => $lastPickedPokemonId,
            ])
            ->log('Draft pick reverted');

        (new DraftTimerAction)(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_START_TURN]);
    }

    private function revertLastDraftSkip(int $leagueId, DraftOrder $skippedOrder): void
    {
        $draft = Draft::where('league_id', $leagueId)->first();
        if ($draft === null) {
            return;
        }

        $skippedOrder->status = 1;
        $skippedOrder->skipped_at = null;
        $skippedOrder->save();

        DraftOrder::query()
            ->where('league_id', $leagueId)
            ->where('round_number', '>', $skippedOrder->round_number)
            ->delete();

        $draft->round_number = $skippedOrder->round_number;
        $draft->pick_number = $skippedOrder->pick_number;
        $draft->save();

        activity()
            ->performedOn($skippedOrder)
            ->withProperties([
                'league_id' => $leagueId,
                'team_id' => $skippedOrder->team_id,
                'round_number' => $skippedOrder->round_number,
                'pick_number' => $skippedOrder->pick_number,
            ])
            ->log('Draft pick skip reverted');

        (new DraftTimerAction)(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_START_TURN]);
    }

    private function revertLastBanSkip(int $leagueId, BanOrder $skippedBanOrder): void
    {
        $draft = Draft::where('league_id', $leagueId)->first();
        if ($draft === null) {
            return;
        }

        $skippedBanOrder->status = 1;
        $skippedBanOrder->skipped_at = null;
        $skippedBanOrder->save();

        $this->restoreBanPhaseIfTransitioned($leagueId, $draft);

        activity()
            ->performedOn($skippedBanOrder)
            ->withProperties([
                'league_id' => $leagueId,
                'team_id' => $skippedBanOrder->team_id,
                'round_number' => $skippedBanOrder->round_number,
                'ban_number' => $skippedBanOrder->ban_number,
            ])
            ->log('Draft ban skip reverted');

        (new DraftTimerAction)(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_START_TURN]);
    }

    private function revertLastBan(int $leagueId, Bans $lastBan): void
    {
        $draft = Draft::where('league_id', $leagueId)->first();
        if ($draft === null) {
            return;
        }

        $teamId = (int) $lastBan->team_id;
        $bannedPokedexId = (int) $lastBan->pokedex_id;
        $roundNumber = (int) $lastBan->round_number;

        $lastBan->pokedex_id = null;
        $lastBan->status = 0;
        $lastBan->save();

        $banLeaguePokemon = LeaguePokemon::query()
            ->where('league_id', $leagueId)
            ->where('pokedex_id', $bannedPokedexId)
            ->first();
        if ($banLeaguePokemon !== null) {
            $banLeaguePokemon->banned = false;
            $banLeaguePokemon->save();
        }

        $banOrder = BanOrder::query()
            ->where('league_id', $leagueId)
            ->where('team_id', $teamId)
            ->where('round_number', $roundNumber)
            ->where('status', 0)
            ->whereNull('skipped_at')
            ->orderBy('ban_number', 'desc')
            ->first();

        if ($banOrder !== null) {
            $banOrder->status = 1;
            $banOrder->save();
        }

        $this->restoreBanPhaseIfTransitioned($leagueId, $draft);

        activity()
            ->performedOn($lastBan)
            ->withProperties([
                'league_id' => $leagueId,
                'team_id' => $teamId,
                'pokedex_id' => $bannedPokedexId,
                'round_number' => $roundNumber,
            ])
            ->log('Draft ban reverted');

        (new DraftTimerAction)(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_START_TURN]);
    }

    /**
     * If the draft has already advanced to status=1 (draft phase) and no picks have
     * happened yet, roll it back to status=2 (ban phase) and drop the auto-created
     * round-1 draft orders.
     */
    private function restoreBanPhaseIfTransitioned(int $leagueId, Draft $draft): void
    {
        if ((int) $draft->status !== 1) {
            return;
        }

        $draftPickCount = DraftPick::query()->where('league_id', $leagueId)->count();
        if ($draftPickCount !== 0) {
            return;
        }

        DraftOrder::query()->where('league_id', $leagueId)->delete();
        $draft->status = 2;
        $draft->round_number = 1;
        $draft->pick_number = 1;
        $draft->save();
    }
}

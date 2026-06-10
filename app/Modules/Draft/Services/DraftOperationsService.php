<?php

namespace App\Modules\Draft\Services;

use App\Events\DraftDetailEvent;
use App\Kernel\Contracts\DraftOperations;
use App\Modules\Draft\Actions\BanPokemonAction;
use App\Modules\Draft\Actions\CreateEditDraftAction;
use App\Modules\Draft\Actions\DraftPokemonAction;
use App\Modules\Draft\Actions\DraftTimerAction;
use App\Modules\Draft\Actions\ReadCurrentDraftAction;
use App\Modules\Draft\Actions\ReorderDraftWishlistAction;
use App\Modules\Draft\Actions\SkipCurrentTurnAction;
use App\Modules\Draft\Actions\StartDraftAction;
use App\Modules\Draft\Actions\ToggleDraftWishlistAction;
use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\Draft\Models\DraftWishlistItem;
use App\Modules\League\Actions\ReadLeagueDraftAction;
use App\Modules\League\Actions\ReadLeaguePokemonAction;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;

class DraftOperationsService implements DraftOperations
{
    public function __construct(
        private ReadCurrentDraftAction $readCurrentDraftAction,
        private ReadLeaguePokemonAction $readLeaguePokemonAction,
        private ToggleDraftWishlistAction $toggleDraftWishlistAction,
        private ReorderDraftWishlistAction $reorderDraftWishlistAction,
        private StartDraftAction $startDraftAction,
        private BanPokemonAction $banPokemonAction,
        private ReadLeagueDraftAction $readLeagueDraftAction,
        private DraftPokemonAction $draftPokemonAction,
        private CreateEditDraftAction $createEditDraftAction,
        private DraftTimerAction $draftTimerAction,
        private SkipCurrentTurnAction $skipCurrentTurnAction,
    ) {}

    public function indexOutcome(int $leagueId, int $userId): array
    {
        $draft = Draft::query()->where('league_id', $leagueId)->first();
        if ($draft !== null && (int) $draft->status === 0) {
            return [
                'type' => 'redirect',
                'league_id' => $leagueId,
            ];
        }

        $league = League::query()->with('draftConfig')->findOrFail($leagueId);
        $pokemon = ($this->readLeaguePokemonAction)(['league_id' => $leagueId, 'command' => 'all_with_status']);
        $costHeaders = $pokemon->unique('cost')->pluck('cost')->sortDesc()->values();
        $teams = ($this->readCurrentDraftAction)(['league_id' => $leagueId, 'command' => 'teams']);
        $userTeam = Team::query()
            ->where('user_id', $userId)
            ->select('id', 'admin_flag')
            ->where('league_id', $leagueId)
            ->whereNull('dropped_at')
            ->first();
        $wishlistLeaguePokemonIds = $userTeam !== null
            ? DraftWishlistItem::query()
                ->where('team_id', $userTeam->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('league_pokemon_id')
                ->all()
            : [];
        $canManageDraftAsAdmin = (int) $userId === (int) $league->league_owner
            || ($userTeam !== null && (int) $userTeam->admin_flag === 1);

        $currentBanner = null;
        $banOrders = collect([]);
        $draftorder = collect([]);
        $currentpicker = null;
        $allBans = ($this->readCurrentDraftAction)(['league_id' => $leagueId, 'command' => 'allbans']);

        if ($draft && $draft->status === 2) {
            $currentBanner = ($this->readCurrentDraftAction)(['league_id' => $leagueId, 'command' => 'currentbanner']);
            $banOrders = ($this->readCurrentDraftAction)(['league_id' => $leagueId, 'command' => 'banorder']);
        } else {
            $draftorder = ($this->readCurrentDraftAction)(['league_id' => $leagueId, 'command' => 'draftorder']);
            $currentpicker = ($this->readCurrentDraftAction)(['league_id' => $leagueId, 'command' => 'currentpicker']);
        }

        $lastPick = $draft ? ($this->readCurrentDraftAction)(['league_id' => $leagueId, 'command' => 'lastpick']) : null;
        $lastSkip = $draft ? ($this->readCurrentDraftAction)(['league_id' => $leagueId, 'command' => 'lastskip']) : null;
        $lastBan = $draft ? ($this->readCurrentDraftAction)(['league_id' => $leagueId, 'command' => 'lastban']) : null;
        $lastBanSkip = $draft ? ($this->readCurrentDraftAction)(['league_id' => $leagueId, 'command' => 'lastbanskip']) : null;

        return [
            'type' => 'page',
            'props' => [
                'league' => fn () => $league,
                'draftConfig' => fn () => $league->draftConfig,
                'pokemon' => fn () => $pokemon,
                'costHeaders' => fn () => $costHeaders,
                'draftOrders' => fn () => $draftorder,
                'currentPicker' => fn () => $currentpicker,
                'currentBanner' => fn () => $currentBanner,
                'banOrders' => fn () => $banOrders,
                'lastPick' => fn () => $lastPick,
                'lastBan' => fn () => $lastBan,
                'lastSkip' => fn () => $lastSkip,
                'lastBanSkip' => fn () => $lastBanSkip,
                'allBans' => fn () => $allBans,
                'userTeam' => fn () => $userTeam,
                'canManageDraftAsAdmin' => fn () => $canManageDraftAsAdmin,
                'teams' => fn () => $teams,
                'draft' => fn () => $draft,
                'wishlist_league_pokemon_ids' => fn () => $wishlistLeaguePokemonIds,
            ],
        ];
    }

    public function toggleWishlist(int $teamId, int $leaguePokemonId): int
    {
        $team = Team::query()->findOrFail($teamId);
        ($this->toggleDraftWishlistAction)($team, $leaguePokemonId);

        return (int) $team->league_id;
    }

    public function reorderWishlist(int $teamId, array $orderedLeaguePokemonIds): int
    {
        $team = Team::query()->findOrFail($teamId);
        ($this->reorderDraftWishlistAction)($team, $orderedLeaguePokemonIds);

        return (int) $team->league_id;
    }

    public function startDraft(int $leagueId): int
    {
        ($this->startDraftAction)($leagueId);

        return $leagueId;
    }

    public function ban(int $leagueId, int $userId, int $pokemonId): array
    {
        $team = Team::query()->where('user_id', $userId)->where('league_id', $leagueId)->first();

        if (! $team) {
            return [
                'league_id' => $leagueId,
                'errors' => ['error' => 'Team not found for this user and league.'],
            ];
        }

        $draft = Draft::query()->where('league_id', $leagueId)->first();

        if (! $draft || $draft->status !== 2) {
            return [
                'league_id' => $leagueId,
                'errors' => ['error' => 'Draft is not in the ban phase.'],
            ];
        }

        $currentBanOrder = BanOrder::query()->where('league_id', $leagueId)
            ->where('status', 1)
            ->orderBy('round_number', 'asc')
            ->orderBy('ban_number', 'asc')
            ->first();

        if (! $currentBanOrder || $currentBanOrder->team_id !== $team->id) {
            return [
                'league_id' => $leagueId,
                'errors' => ['error' => 'It is not your turn to ban.'],
            ];
        }

        ($this->banPokemonAction)(['league_id' => $leagueId, 'team_id' => $team->id, 'pokemon_id' => $pokemonId]);
        ($this->readLeagueDraftAction)(['league_id' => $leagueId, 'command' => 'broadcastdraft', 'end_draft' => 0]);

        return ['league_id' => $leagueId];
    }

    public function pick(int $leagueId, int $userId, int $pokemonId, int $pokemonCost): array
    {
        $team = Team::query()->where('user_id', $userId)->where('league_id', $leagueId)->first();
        if (! $team) {
            return [
                'league_id' => $leagueId,
                'errors' => ['error' => 'Team not found for this user and league.'],
            ];
        }

        $draft = Draft::query()->where('league_id', $leagueId)->first();
        if (! $draft) {
            return [
                'league_id' => $leagueId,
                'errors' => ['error' => 'Draft not found for this league.'],
            ];
        }

        $league = League::query()->with('draftConfig')->find($leagueId);
        if (! $league) {
            return [
                'league_id' => $leagueId,
                'errors' => ['error' => 'League not found.'],
            ];
        }

        $picksMadeByTeam = DraftPick::query()
            ->where('league_id', $leagueId)
            ->where('team_id', $team->id)
            ->count();
        $mandatoryPicks = max(0, (int) $league->draftConfig->minimum_drafts - $picksMadeByTeam - 1);
        $draftOrder = DraftOrder::query()->where('league_id', $leagueId)->where('team_id', $team->id)->where('status', 1)->first();
        if (! $draftOrder) {
            return [
                'league_id' => $leagueId,
                'errors' => ['error' => 'Draft order not found for this team.'],
            ];
        }

        try {
            ($this->draftPokemonAction)([
                'league_id' => $leagueId,
                'team_id' => $team->id,
                'pokemon_cost' => $pokemonCost,
                'pokemon_id' => $pokemonId,
                'is_last_pick' => $draftOrder->is_last_pick,
                'draft_id' => $draft->id,
                'round_number' => $draft->round_number,
                'pick_number' => $draftOrder->pick_number,
                'mandatory_picks' => $mandatoryPicks,
            ]);
        } catch (\Exception $e) {
            return [
                'league_id' => $leagueId,
                'errors' => ['error' => $e->getMessage()],
                'back' => true,
            ];
        }

        ($this->readLeagueDraftAction)(['league_id' => $leagueId, 'command' => 'broadcastdraft', 'end_draft' => 0]);

        return ['league_id' => $leagueId];
    }

    public function revertLastPick(int $leagueId): int
    {
        ($this->createEditDraftAction)(['league_id' => $leagueId, 'command' => 'revert_last_pick']);
        ($this->readLeagueDraftAction)(['league_id' => $leagueId, 'command' => 'broadcastdraft', 'end_draft' => 0]);

        return $leagueId;
    }

    public function abortDraft(int $leagueId): int
    {
        ($this->createEditDraftAction)(['league_id' => $leagueId, 'command' => 'abort_draft']);
        ($this->readLeagueDraftAction)(['league_id' => $leagueId, 'command' => 'broadcastdraft', 'end_draft' => 1]);

        return $leagueId;
    }

    public function pauseTimer(int $leagueId): int
    {
        ($this->draftTimerAction)(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_PAUSE]);

        activity()
            ->withProperties(['league_id' => $leagueId])
            ->log('Draft timer paused by commissioner');

        DraftDetailEvent::dispatch(['league_id' => $leagueId, 'end_draft' => 0]);

        return $leagueId;
    }

    public function resumeTimer(int $leagueId): int
    {
        ($this->draftTimerAction)(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_RESUME]);

        activity()
            ->withProperties(['league_id' => $leagueId])
            ->log('Draft timer resumed by commissioner');

        DraftDetailEvent::dispatch(['league_id' => $leagueId, 'end_draft' => 0]);

        return $leagueId;
    }

    public function adjustTimer(int $leagueId, int $deltaSeconds): int
    {
        ($this->draftTimerAction)([
            'league_id' => $leagueId,
            'command' => DraftTimerAction::COMMAND_ADJUST,
            'delta_seconds' => $deltaSeconds,
        ]);

        activity()
            ->withProperties(['league_id' => $leagueId, 'delta_seconds' => $deltaSeconds])
            ->log('Draft timer adjusted by commissioner');

        DraftDetailEvent::dispatch(['league_id' => $leagueId, 'end_draft' => 0]);

        return $leagueId;
    }

    public function forceSkip(int $leagueId): int
    {
        ($this->skipCurrentTurnAction)([
            'league_id' => $leagueId,
            'reason' => 'commissioner_force_skip',
        ]);

        return $leagueId;
    }
}

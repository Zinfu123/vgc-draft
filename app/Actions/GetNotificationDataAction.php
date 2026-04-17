<?php

namespace App\Actions;

use App\Models\User;
use App\Modules\Matches\Enums\ScheduleRequestStatus;
use App\Modules\Matches\Models\MatchMessage;
use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Teams\Models\Team;
use App\Modules\Trade\Models\Trade;
use Illuminate\Support\Collection;

class GetNotificationDataAction
{
    /**
     * @return array{
     *     unread_messages: int,
     *     pending_trades: int,
     *     pending_schedules: int,
     *     items: array<int, array{id: int, type: string, title: string, body: string, href: string, created_at: string}>
     * }
     */
    public function __invoke(User $user): array
    {
        $userTeamIds = Team::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        $unreadMessageCount = $this->countUnreadMessages($user->id, $userTeamIds);
        $pendingTradeCount = $this->countPendingTrades($userTeamIds);
        $pendingScheduleCount = $this->countPendingSchedules($user->id, $userTeamIds);

        $items = $this->buildNotificationItems($user->id, $userTeamIds);

        return [
            'unread_messages' => $unreadMessageCount,
            'pending_trades' => $pendingTradeCount,
            'pending_schedules' => $pendingScheduleCount,
            'items' => $items,
        ];
    }

    /**
     * @param  Collection<int, int>  $userTeamIds
     */
    private function countUnreadMessages(int $userId, Collection $userTeamIds): int
    {
        if ($userTeamIds->isEmpty()) {
            return 0;
        }

        return MatchMessage::query()
            ->where('is_read', false)
            ->where('user_id', '!=', $userId)
            ->whereHas('set', function ($query) use ($userTeamIds): void {
                $query->where(function ($q) use ($userTeamIds): void {
                    $q->whereIn('team1_id', $userTeamIds)
                        ->orWhereIn('team2_id', $userTeamIds);
                });
            })
            ->count();
    }

    /**
     * @param  Collection<int, int>  $userTeamIds
     */
    private function countPendingTrades(Collection $userTeamIds): int
    {
        if ($userTeamIds->isEmpty()) {
            return 0;
        }

        return Trade::query()
            ->where('status', 'pending')
            ->whereIn('target_team_id', $userTeamIds)
            ->count();
    }

    /**
     * @param  Collection<int, int>  $userTeamIds
     */
    private function countPendingSchedules(int $userId, Collection $userTeamIds): int
    {
        if ($userTeamIds->isEmpty()) {
            return 0;
        }

        return MatchScheduleRequest::query()
            ->where('status', ScheduleRequestStatus::Pending->value)
            ->where('proposed_by_user_id', '!=', $userId)
            ->whereHas('set', function ($query) use ($userTeamIds): void {
                $query->where(function ($q) use ($userTeamIds): void {
                    $q->whereIn('team1_id', $userTeamIds)
                        ->orWhereIn('team2_id', $userTeamIds);
                });
            })
            ->count();
    }

    /**
     * @param  Collection<int, int>  $userTeamIds
     * @return array<int, array{id: int, type: string, title: string, body: string, href: string, created_at: string}>
     */
    private function buildNotificationItems(int $userId, Collection $userTeamIds): array
    {
        if ($userTeamIds->isEmpty()) {
            return [];
        }

        $messages = $this->buildMessageItems($userId, $userTeamIds);
        $trades = $this->buildTradeItems($userTeamIds);
        $schedules = $this->buildScheduleItems($userId, $userTeamIds);

        return collect($messages)
            ->merge($trades)
            ->merge($schedules)
            ->sortByDesc('created_at')
            ->take(15)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $userTeamIds
     * @return array<int, array{id: int, type: string, title: string, body: string, href: string, created_at: string}>
     */
    private function buildMessageItems(int $userId, Collection $userTeamIds): array
    {
        return MatchMessage::query()
            ->where('is_read', false)
            ->where('user_id', '!=', $userId)
            ->whereHas('set', function ($query) use ($userTeamIds): void {
                $query->where(function ($q) use ($userTeamIds): void {
                    $q->whereIn('team1_id', $userTeamIds)
                        ->orWhereIn('team2_id', $userTeamIds);
                });
            })
            ->with(['user:id,name', 'set:id'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (MatchMessage $msg) => [
                'id' => $msg->id,
                'type' => 'message',
                'title' => $msg->user?->name ?? 'Unknown',
                'body' => $msg->body,
                'href' => route('sets.show', ['set_id' => $msg->set_id]),
                'created_at' => $msg->created_at?->toISOString() ?? '',
            ])
            ->all();
    }

    /**
     * @param  Collection<int, int>  $userTeamIds
     * @return array<int, array{id: int, type: string, title: string, body: string, href: string, created_at: string}>
     */
    private function buildTradeItems(Collection $userTeamIds): array
    {
        return Trade::query()
            ->where('status', 'pending')
            ->whereIn('target_team_id', $userTeamIds)
            ->with(['requestingTeam:id,name,league_id', 'league:id'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Trade $trade) => [
                'id' => $trade->id,
                'type' => 'trade',
                'title' => 'Trade offer from '.($trade->requestingTeam?->name ?? 'Unknown'),
                'body' => 'Pending trade offer — click to review.',
                'href' => route('leagues.dashboard', ['league' => $trade->league_id]),
                'created_at' => $trade->created_at?->toISOString() ?? '',
            ])
            ->all();
    }

    /**
     * @param  Collection<int, int>  $userTeamIds
     * @return array<int, array{id: int, type: string, title: string, body: string, href: string, created_at: string}>
     */
    private function buildScheduleItems(int $userId, Collection $userTeamIds): array
    {
        return MatchScheduleRequest::query()
            ->where('status', ScheduleRequestStatus::Pending->value)
            ->where('proposed_by_user_id', '!=', $userId)
            ->whereHas('set', function ($query) use ($userTeamIds): void {
                $query->where(function ($q) use ($userTeamIds): void {
                    $q->whereIn('team1_id', $userTeamIds)
                        ->orWhereIn('team2_id', $userTeamIds);
                });
            })
            ->with(['proposedByUser:id,name', 'set:id'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (MatchScheduleRequest $req) => [
                'id' => $req->id,
                'type' => 'schedule',
                'title' => ($req->proposedByUser?->name ?? 'Someone').' proposed a match time',
                'body' => $req->proposed_at?->toDayDateTimeString() ?? 'See match for details.',
                'href' => route('sets.show', ['set_id' => $req->set_id]),
                'created_at' => $req->created_at?->toISOString() ?? '',
            ])
            ->all();
    }
}

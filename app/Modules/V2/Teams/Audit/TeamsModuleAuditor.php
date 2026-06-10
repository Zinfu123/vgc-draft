<?php

namespace App\Modules\V2\Teams\Audit;

use App\Kernel\Audit\ModuleAuditResult;
use App\Kernel\Contracts\ModuleAuditor;
use Illuminate\Support\Facades\DB;

class TeamsModuleAuditor implements ModuleAuditor
{
    public function module(): string
    {
        return 'Teams';
    }

    public function audit(): ModuleAuditResult
    {
        $rowCounts = [
            'teams' => DB::table('teams')->count(),
            'leagues' => DB::table('leagues')->count(),
        ];

        $issues = [];

        $orphanedTeams = DB::table('teams')
            ->leftJoin('leagues', 'teams.league_id', '=', 'leagues.id')
            ->whereNull('leagues.id')
            ->count();

        if ($orphanedTeams > 0) {
            $issues[] = [
                'check' => 'orphaned_teams',
                'message' => "{$orphanedTeams} teams reference missing leagues.",
            ];
        }

        $duplicateCoachInLeague = DB::table('teams')
            ->select('league_id', 'user_id')
            ->whereNotNull('user_id')
            ->groupBy('league_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplicateCoachInLeague > 0) {
            $issues[] = [
                'check' => 'duplicate_coach_per_league',
                'message' => "{$duplicateCoachInLeague} league/coach pairs have more than one team.",
            ];
        }

        return new ModuleAuditResult($this->module(), $rowCounts, $issues);
    }
}

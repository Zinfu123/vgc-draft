<?php

namespace App\Modules\V2\League\Audit;

use App\Kernel\Audit\ModuleAuditResult;
use App\Kernel\Contracts\ModuleAuditor;
use Illuminate\Support\Facades\DB;

class LeagueModuleAuditor implements ModuleAuditor
{
    public function module(): string
    {
        return 'League';
    }

    public function audit(): ModuleAuditResult
    {
        $rowCounts = [
            'leagues' => DB::table('leagues')->count(),
            'teams' => DB::table('teams')->count(),
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

        return new ModuleAuditResult($this->module(), $rowCounts, $issues);
    }
}

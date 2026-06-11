<?php

namespace App\Modules\V2\Playoffs\Audit;

use App\Kernel\Audit\ModuleAuditResult;
use App\Kernel\Contracts\ModuleAuditor;
use Illuminate\Support\Facades\DB;

class PlayoffsModuleAuditor implements ModuleAuditor
{
    public function module(): string
    {
        return 'Playoffs';
    }

    public function audit(): ModuleAuditResult
    {
        $rowCounts = [
            'playoffs' => DB::table('playoffs')->count(),
            'playoff_matches' => DB::table('playoff_matches')->count(),
        ];

        $issues = [];

        $orphanedPlayoffs = DB::table('playoffs')
            ->leftJoin('leagues', 'playoffs.league_id', '=', 'leagues.id')
            ->whereNull('leagues.id')
            ->count();

        if ($orphanedPlayoffs > 0) {
            $issues[] = [
                'check' => 'orphaned_playoffs',
                'message' => "{$orphanedPlayoffs} playoffs reference missing leagues.",
            ];
        }

        $orphanedMatches = DB::table('playoff_matches')
            ->leftJoin('playoffs', 'playoff_matches.playoff_id', '=', 'playoffs.id')
            ->whereNull('playoffs.id')
            ->count();

        if ($orphanedMatches > 0) {
            $issues[] = [
                'check' => 'orphaned_playoff_matches',
                'message' => "{$orphanedMatches} playoff_matches reference missing playoffs.",
            ];
        }

        return new ModuleAuditResult($this->module(), $rowCounts, $issues);
    }
}

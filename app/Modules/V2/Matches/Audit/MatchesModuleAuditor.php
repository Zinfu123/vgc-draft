<?php

namespace App\Modules\V2\Matches\Audit;

use App\Kernel\Audit\ModuleAuditResult;
use App\Kernel\Contracts\ModuleAuditor;
use Illuminate\Support\Facades\DB;

class MatchesModuleAuditor implements ModuleAuditor
{
    public function module(): string
    {
        return 'Matches';
    }

    public function audit(): ModuleAuditResult
    {
        $rowCounts = [
            'sets' => DB::table('sets')->count(),
            'pools' => DB::table('pools')->count(),
            'match_configs' => DB::table('match_configs')->count(),
        ];

        $issues = [];

        $orphanedSets = DB::table('sets')
            ->leftJoin('leagues', 'sets.league_id', '=', 'leagues.id')
            ->whereNull('leagues.id')
            ->count();

        if ($orphanedSets > 0) {
            $issues[] = [
                'check' => 'orphaned_sets',
                'message' => "{$orphanedSets} sets reference missing leagues.",
            ];
        }

        $orphanedPools = DB::table('pools')
            ->leftJoin('leagues', 'pools.league_id', '=', 'leagues.id')
            ->whereNull('leagues.id')
            ->count();

        if ($orphanedPools > 0) {
            $issues[] = [
                'check' => 'orphaned_pools',
                'message' => "{$orphanedPools} pools reference missing leagues.",
            ];
        }

        return new ModuleAuditResult($this->module(), $rowCounts, $issues);
    }
}

<?php

namespace App\Modules\V2\TeamCoverage\Audit;

use App\Kernel\Audit\ModuleAuditResult;
use App\Kernel\Contracts\ModuleAuditor;
use Illuminate\Support\Facades\DB;

class TeamCoverageModuleAuditor implements ModuleAuditor
{
    public function module(): string
    {
        return 'TeamCoverage';
    }

    public function audit(): ModuleAuditResult
    {
        $rowCounts = [
            'teams' => DB::table('teams')->count(),
            'league_pokemon' => DB::table('league_pokemon')->count(),
            'pokemon_generation_data' => DB::table('pokemon_generation_data')->count(),
        ];

        $issues = [];

        $draftedWithoutTeam = DB::table('league_pokemon')
            ->where('is_drafted', true)
            ->whereNotNull('drafted_by')
            ->leftJoin('teams', 'league_pokemon.drafted_by', '=', 'teams.id')
            ->whereNull('teams.id')
            ->count();

        if ($draftedWithoutTeam > 0) {
            $issues[] = [
                'check' => 'orphaned_drafted_league_pokemon',
                'message' => "{$draftedWithoutTeam} drafted league_pokemon rows reference missing teams.",
            ];
        }

        return new ModuleAuditResult($this->module(), $rowCounts, $issues);
    }
}

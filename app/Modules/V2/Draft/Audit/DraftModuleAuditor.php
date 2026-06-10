<?php

namespace App\Modules\V2\Draft\Audit;

use App\Kernel\Audit\ModuleAuditResult;
use App\Kernel\Contracts\ModuleAuditor;
use Illuminate\Support\Facades\DB;

class DraftModuleAuditor implements ModuleAuditor
{
    public function module(): string
    {
        return 'Draft';
    }

    public function audit(): ModuleAuditResult
    {
        $rowCounts = [
            'drafts' => DB::table('drafts')->count(),
            'draft_picks' => DB::table('draft_picks')->count(),
            'draft_order' => DB::table('draft_order')->count(),
            'draft_bans' => DB::table('draft_bans')->count(),
        ];

        $issues = [];

        $orphanedDrafts = DB::table('drafts')
            ->leftJoin('leagues', 'drafts.league_id', '=', 'leagues.id')
            ->whereNull('leagues.id')
            ->count();

        if ($orphanedDrafts > 0) {
            $issues[] = [
                'check' => 'orphaned_drafts',
                'message' => "{$orphanedDrafts} drafts reference missing leagues.",
            ];
        }

        $orphanedPicks = DB::table('draft_picks')
            ->leftJoin('drafts', 'draft_picks.draft_id', '=', 'drafts.id')
            ->whereNull('drafts.id')
            ->count();

        if ($orphanedPicks > 0) {
            $issues[] = [
                'check' => 'orphaned_draft_picks',
                'message' => "{$orphanedPicks} draft_picks reference missing drafts.",
            ];
        }

        return new ModuleAuditResult($this->module(), $rowCounts, $issues);
    }
}

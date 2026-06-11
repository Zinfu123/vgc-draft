<?php

namespace App\Modules\V2\Trade\Audit;

use App\Kernel\Audit\ModuleAuditResult;
use App\Kernel\Contracts\ModuleAuditor;
use Illuminate\Support\Facades\DB;

class TradeModuleAuditor implements ModuleAuditor
{
    public function module(): string
    {
        return 'Trade';
    }

    public function audit(): ModuleAuditResult
    {
        $rowCounts = [
            'trades' => DB::table('trades')->count(),
            'trade_pokemon' => DB::table('trade_pokemon')->count(),
        ];

        $issues = [];

        $orphanedTrades = DB::table('trades')
            ->leftJoin('leagues', 'trades.league_id', '=', 'leagues.id')
            ->whereNull('leagues.id')
            ->count();

        if ($orphanedTrades > 0) {
            $issues[] = [
                'check' => 'orphaned_trades',
                'message' => "{$orphanedTrades} trades reference missing leagues.",
            ];
        }

        $orphanedTradePokemon = DB::table('trade_pokemon')
            ->leftJoin('trades', 'trade_pokemon.trade_id', '=', 'trades.id')
            ->whereNull('trades.id')
            ->count();

        if ($orphanedTradePokemon > 0) {
            $issues[] = [
                'check' => 'orphaned_trade_pokemon',
                'message' => "{$orphanedTradePokemon} trade_pokemon rows reference missing trades.",
            ];
        }

        return new ModuleAuditResult($this->module(), $rowCounts, $issues);
    }
}

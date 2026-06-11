<?php

namespace App\Modules\V2\Trade\Providers;

use App\Kernel\Audit\ModuleAuditRegistry;
use App\Modules\V2\Trade\Audit\TradeModuleAuditor;
use Illuminate\Support\ServiceProvider;

class TradeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->make(ModuleAuditRegistry::class)
            ->register($this->app->make(TradeModuleAuditor::class));
    }
}

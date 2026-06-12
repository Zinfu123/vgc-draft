<?php

namespace App\Modules\V2\League\Providers;

use App\Kernel\Audit\ModuleAuditRegistry;
use App\Modules\V2\League\Audit\LeagueModuleAuditor;
use Illuminate\Support\ServiceProvider;

class LeagueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->make(ModuleAuditRegistry::class)
            ->register($this->app->make(LeagueModuleAuditor::class));
    }
}

<?php

namespace App\Modules\V2\TeamCoverage\Providers;

use App\Kernel\Audit\ModuleAuditRegistry;
use App\Modules\V2\TeamCoverage\Audit\TeamCoverageModuleAuditor;
use Illuminate\Support\ServiceProvider;

class TeamCoverageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->make(ModuleAuditRegistry::class)
            ->register($this->app->make(TeamCoverageModuleAuditor::class));
    }
}
